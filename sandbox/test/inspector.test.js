'use strict';

const assert = require('node:assert/strict');
const http = require('node:http');
const test = require('node:test');
const { analyzeHtml, inspectUrl, isBlockedIPv4 } = require('../src/inspector');

test('blocks internal and special IPv4 ranges', () => {
  for (const address of [
    '0.0.0.0', '10.0.0.1', '100.64.0.1', '127.0.0.1',
    '169.254.169.254', '172.16.0.1', '192.0.2.1', '192.168.1.1',
    '198.18.0.1', '198.51.100.1', '203.0.113.1', '224.0.0.1',
  ]) {
    assert.equal(isBlockedIPv4(address), true, address);
  }
  assert.equal(isBlockedIPv4('8.8.8.8'), false);
});

test('extracts bounded metadata without executing scripts', () => {
  const metadata = analyzeHtml(`
    <html><head><title> Example &amp; Login </title>
    <meta name="description" content="A test page">
    <meta http-equiv="refresh" content="0; url=https://other.example/">
    </head><body>
    <form action="https://other.example/collect"><input type="password"></form>
    <script>throw new Error('must not execute')</script><iframe></iframe>
    Verify your account
    </body></html>
  `, 'https://example.com/');
  assert.equal(metadata.title, 'Example & Login');
  assert.equal(metadata.forms, 1);
  assert.equal(metadata.password_fields, 1);
  assert.equal(metadata.external_form_actions, 1);
  assert.equal(metadata.meta_refresh, true);
  assert.equal(metadata.scripts, 1);
  assert.deepEqual(metadata.phishing_terms, ['verify your account']);
});

test('does not follow redirects', async () => {
  const server = http.createServer((_request, response) => {
    response.writeHead(302, { Location: 'http://127.0.0.1/private' });
    response.end();
  });
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  const { port } = server.address();
  await assert.rejects(
    () => inspectUrl(`http://127.0.0.1:${port}/`),
    /blocked_port|blocked_address/
  );
  await new Promise((resolve) => server.close(resolve));
});

test('rejects nonstandard ports before making a request', async () => {
  await assert.rejects(() => inspectUrl('https://example.com:444/'), /blocked_port/);
});
