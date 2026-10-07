'use strict';

const fs = require('node:fs');
const assert = require('node:assert/strict');
const SwaggerParser = require('@apidevtools/swagger-parser');

async function main() {
  const filename = process.argv[2];
  if (!filename) throw new Error('Informe o JSON exportado do OpenAPI.');
  const spec = JSON.parse(fs.readFileSync(filename === '-' ? 0 : filename, 'utf8'));
  await SwaggerParser.validate(spec, { resolve: { external: false } });
  const ids = Object.values(spec.paths).flatMap((path) =>
    Object.values(path).map((operation) => operation.operationId));
  assert.equal(new Set(ids).size, ids.length, 'operationId duplicado');
  assert.ok(Object.values(spec.paths).every((path) =>
    Object.values(path).every((operation) => operation.security.length === 0 ||
      operation.security.some((scheme) => Object.hasOwn(scheme, 'sessionCookie')))));
  process.stdout.write(`OpenAPI válido: ${ids.length} operações.\n`);
}

main().catch((error) => {
  process.stderr.write(`${error.message}\n`);
  process.exitCode = 1;
});
