#!/usr/bin/env node
'use strict';
// Reads MJML from stdin, writes compiled HTML to stdout.
// Exit 0 on success, 1 on error (error message to stderr).
async function main() {
    const mjml2html = require(__dirname + '/node_modules/mjml/lib');
    let input = '';
    process.stdin.setEncoding('utf8');
    for await (const chunk of process.stdin) { input += chunk; }
    if (!input.trim()) { process.stderr.write('Empty input\n'); process.exit(1); }
    const result = await mjml2html(input, { validationLevel: 'soft' });
    if (!result || !result.html) { process.stderr.write('Compilation failed\n'); process.exit(1); }
    process.stdout.write(result.html);
}
main().catch(e => { process.stderr.write(e.message + '\n'); process.exit(1); });
