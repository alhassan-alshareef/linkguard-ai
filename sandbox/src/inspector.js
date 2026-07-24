'use strict';

const dns = require('node:dns').promises;
const http = require('node:http');
const https = require('node:https');
const net = require('node:net');

const DEFAULT_MAX_BYTES = 128 * 1024;
const DEFAULT_TIMEOUT_MS = 6000;

function isBlockedIPv4(address) {
  const parts = address.split('.').map(Number);
  if (parts.length !== 4 || parts.some((part) => !Number.isInteger(part) || part < 0 || part > 255)) {
    return true;
  }
  const [a, b] = parts;
  return (
    a === 0 ||
    a === 10 ||
    a === 127 ||
    (a === 100 && b >= 64 && b <= 127) ||
    (a === 169 && b === 254) ||
    (a === 172 && b >= 16 && b <= 31) ||
    (a === 192 && b === 0) ||
    (a === 192 && b === 168) ||
    (a === 198 && (b === 18 || b === 19)) ||
    (a === 198 && b === 51) ||
    (a === 203 && b === 0) ||
    a >= 224
  );
}

function decodeEntities(value) {
  const entities = {
    amp: '&',
    lt: '<',
    gt: '>',
    quot: '"',
    apos: "'",
    nbsp: ' ',
  };
  return value
    .replace(/&#(\d+);/g, (_, code) => String.fromCodePoint(Math.min(Number(code), 0x10ffff)))
    .replace(/&#x([0-9a-f]+);/gi, (_, code) => String.fromCodePoint(Math.min(parseInt(code, 16), 0x10ffff)))
    .replace(/&([a-z]+);/gi, (match, name) => entities[name.toLowerCase()] ?? match);
}

function textValue(value, maxLength = 240) {
  return decodeEntities(value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()).slice(0, maxLength);
}

function attribute(tag, name) {
  const match = tag.match(new RegExp(`\\b${name}\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i'));
  return match ? (match[1] ?? match[2] ?? match[3] ?? '') : '';
}

function analyzeHtml(html, pageUrl, headers = {}) {
  const titleMatch = html.match(/<title\b[^>]*>([\s\S]*?)<\/title>/i);
  const title = titleMatch ? textValue(titleMatch[1], 200) : '';
  const metaTags = html.match(/<meta\b[^>]*>/gi) ?? [];
  let description = '';
  let metaRefresh = false;
  for (const tag of metaTags) {
    const name = attribute(tag, 'name').toLowerCase();
    const property = attribute(tag, 'property').toLowerCase();
    const httpEquiv = attribute(tag, 'http-equiv').toLowerCase();
    if (!description && (name === 'description' || property === 'og:description')) {
      description = textValue(attribute(tag, 'content'), 300);
    }
    if (httpEquiv === 'refresh') {
      metaRefresh = true;
    }
  }

  const formTags = html.match(/<form\b[^>]*>/gi) ?? [];
  let externalFormActions = 0;
  for (const tag of formTags) {
    const action = attribute(tag, 'action');
    if (!action) continue;
    try {
      if (new URL(action, pageUrl).origin !== new URL(pageUrl).origin) {
        externalFormActions += 1;
      }
    } catch {
      externalFormActions += 1;
    }
  }

  const passwordFields = (html.match(/<input\b[^>]*\btype\s*=\s*(?:"password"|'password'|password)[^>]*>/gi) ?? []).length;
  const scriptCount = (html.match(/<script\b/gi) ?? []).length;
  const iframeCount = (html.match(/<iframe\b/gi) ?? []).length;
  const plainText = textValue(
    html.replace(/<script\b[\s\S]*?<\/script>/gi, ' ').replace(/<style\b[\s\S]*?<\/style>/gi, ' '),
    5000
  ).toLowerCase();
  const phishingTerms = ['verify your account', 'confirm your password', 'urgent action', 'claim your prize', 'account suspended']
    .filter((term) => plainText.includes(term));

  return {
    title,
    title_status: title ? 'present' : 'missing',
    description,
    forms: formTags.length,
    password_fields: passwordFields,
    external_form_actions: externalFormActions,
    scripts: scriptCount,
    iframes: iframeCount,
    meta_refresh: metaRefresh,
    phishing_terms: phishingTerms,
    response_security: {
      content_security_policy: Boolean(headers['content-security-policy']),
      frame_protection: Boolean(headers['x-frame-options'] || headers['content-security-policy']?.includes('frame-ancestors')),
      content_type_options: String(headers['x-content-type-options'] ?? '').toLowerCase() === 'nosniff',
    },
  };
}

async function resolvePinnedIPv4(hostname) {
  if (net.isIP(hostname)) {
    if (net.isIP(hostname) !== 4 || isBlockedIPv4(hostname)) {
      throw new Error('blocked_address');
    }
    return hostname;
  }

  let addresses;
  try {
    addresses = await dns.resolve4(hostname);
  } catch {
    addresses = [];
  }
  if (!addresses.length) {
    throw new Error('dns_unavailable');
  }
  if (addresses.some(isBlockedIPv4)) {
    throw new Error('blocked_address');
  }
  return addresses[0];
}

async function inspectUrl(input, options = {}) {
  const timeoutMs = Number(options.timeoutMs ?? DEFAULT_TIMEOUT_MS);
  const maxBytes = Number(options.maxBytes ?? DEFAULT_MAX_BYTES);
  let target;
  try {
    target = new URL(input);
  } catch {
    throw new Error('invalid_url');
  }
  if (!['http:', 'https:'].includes(target.protocol) || target.username || target.password) {
    throw new Error('invalid_url');
  }
  if ((target.protocol === 'http:' && target.port && target.port !== '80') ||
      (target.protocol === 'https:' && target.port && target.port !== '443')) {
    throw new Error('blocked_port');
  }

  const pinnedAddress = await resolvePinnedIPv4(target.hostname);
  const transport = target.protocol === 'https:' ? https : http;

  return await new Promise((resolve, reject) => {
    let settled = false;
    const finish = (callback, value) => {
      if (settled) return;
      settled = true;
      callback(value);
    };
    const request = transport.request(target, {
      method: 'GET',
      headers: {
        Accept: 'text/html,application/xhtml+xml;q=0.9',
        'Accept-Encoding': 'identity',
        'User-Agent': 'LinkGuard-Sandbox/1.0',
        Connection: 'close',
      },
      lookup: (_hostname, lookupOptions, callback) => {
        if (lookupOptions?.all) {
          callback(null, [{ address: pinnedAddress, family: 4 }]);
          return;
        }
        callback(null, pinnedAddress, 4);
      },
      servername: target.hostname,
      rejectUnauthorized: true,
    }, (response) => {
      const status = Number(response.statusCode ?? 0);
      const contentType = String(response.headers['content-type'] ?? '').toLowerCase();
      if (status >= 300 && status < 400) {
        response.resume();
        finish(resolve, {
          status: 'available',
          fetch_status: 'redirect_blocked',
          http_status: status,
          redirect_followed: false,
          final_url: target.origin + target.pathname,
          metadata: null,
        });
        return;
      }
      if (!contentType.includes('text/html') && !contentType.includes('application/xhtml+xml')) {
        response.resume();
        finish(resolve, {
          status: 'available',
          fetch_status: 'unsupported_content_type',
          http_status: status,
          redirect_followed: false,
          final_url: target.origin + target.pathname,
          metadata: null,
        });
        return;
      }

      const chunks = [];
      let size = 0;
      response.on('data', (chunk) => {
        size += chunk.length;
        if (size > maxBytes) {
          request.destroy(new Error('response_too_large'));
          return;
        }
        chunks.push(chunk);
      });
      response.on('end', () => {
        const html = Buffer.concat(chunks).toString('utf8');
        finish(resolve, {
          status: 'available',
          fetch_status: 'inspected',
          http_status: status,
          redirect_followed: false,
          final_url: target.origin + target.pathname,
          bytes_read: size,
          metadata: analyzeHtml(html, target.href, response.headers),
        });
      });
    });
    request.setTimeout(timeoutMs, () => request.destroy(new Error('timeout')));
    request.on('error', (error) => finish(reject, error));
    request.end();
  });
}

module.exports = {
  analyzeHtml,
  inspectUrl,
  isBlockedIPv4,
  resolvePinnedIPv4,
};
