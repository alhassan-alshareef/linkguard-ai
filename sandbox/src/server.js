'use strict';

const crypto = require('node:crypto');
const http = require('node:http');
const { inspectUrl } = require('./inspector');

const port = Number(process.env.PORT ?? 8787);
const token = String(process.env.SANDBOX_SHARED_TOKEN ?? '');
const timeoutMs = Number(process.env.SANDBOX_TIMEOUT_MS ?? 6000);
const maxBytes = Number(process.env.SANDBOX_MAX_BYTES ?? 131072);

if (process.env.NODE_ENV === 'production' && token.length < 24) {
  throw new Error('SANDBOX_SHARED_TOKEN must contain at least 24 characters in production.');
}

function authorized(header) {
  const supplied = String(header ?? '').replace(/^Bearer\s+/i, '');
  if (!token || supplied.length !== token.length) return false;
  return crypto.timingSafeEqual(Buffer.from(supplied), Buffer.from(token));
}

function json(response, status, payload) {
  const body = JSON.stringify(payload);
  response.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(body),
    'Cache-Control': 'no-store',
    'X-Content-Type-Options': 'nosniff',
  });
  response.end(body);
}

const server = http.createServer((request, response) => {
  if (request.method === 'GET' && request.url === '/health') {
    json(response, 200, { status: 'ok', isolation: 'metadata-only', javascript: 'disabled', redirects: 'blocked' });
    return;
  }
  if (request.method !== 'POST' || request.url !== '/inspect') {
    json(response, 404, { status: 'error', code: 'not_found' });
    return;
  }
  if (!authorized(request.headers.authorization)) {
    json(response, 401, { status: 'error', code: 'unauthorized' });
    return;
  }

  const chunks = [];
  let size = 0;
  request.on('data', (chunk) => {
    size += chunk.length;
    if (size > 4096) {
      request.destroy();
      return;
    }
    chunks.push(chunk);
  });
  request.on('end', async () => {
    let payload;
    try {
      payload = JSON.parse(Buffer.concat(chunks).toString('utf8'));
    } catch {
      json(response, 400, { status: 'error', code: 'invalid_json' });
      return;
    }
    try {
      const result = await inspectUrl(String(payload.url ?? ''), { timeoutMs, maxBytes });
      json(response, 200, result);
    } catch (error) {
      const safeCodes = new Set([
        'invalid_url', 'blocked_address', 'blocked_port', 'dns_unavailable',
        'timeout', 'response_too_large',
      ]);
      const code = safeCodes.has(error.message) ? error.message : 'fetch_failed';
      json(response, 422, { status: 'unavailable', code });
    }
  });
});

server.requestTimeout = timeoutMs + 2000;
server.headersTimeout = timeoutMs + 3000;
server.listen(port, '0.0.0.0');
