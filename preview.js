// Minimal static server for previewing dist/ locally. No dependencies.
//   node preview.js     ->  http://localhost:8080
//
// Opening dist/index.html directly with file:// will NOT work correctly:
// browsers block @font-face loads over file:// on CORS grounds, so the
// self-hosted fonts silently fall back. Serve over HTTP instead.
const http = require('http');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, 'dist');
const PORT = 8080;

const TYPES = {
    '.html': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'text/javascript; charset=utf-8',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.webp': 'image/webp',
    '.ttf': 'font/ttf',
    '.woff2': 'font/woff2',
    '.php': 'text/plain; charset=utf-8',
};

http.createServer((req, res) => {
    const urlPath = decodeURIComponent(req.url.split('?')[0]);
    let file = path.join(ROOT, urlPath === '/' ? 'index.html' : urlPath);

    // Keep requests inside dist/.
    if (!file.startsWith(ROOT)) {
        res.writeHead(403).end('Forbidden');
        return;
    }

    fs.readFile(file, (err, data) => {
        if (err) {
            res.writeHead(404, { 'Content-Type': 'text/plain' });
            res.end('404: ' + urlPath);
            return;
        }
        res.writeHead(200, { 'Content-Type': TYPES[path.extname(file).toLowerCase()] || 'application/octet-stream' });
        res.end(data);
    });
}).listen(PORT, () => {
    console.log('Preview running at http://localhost:' + PORT);
    console.log('Serving: ' + ROOT);
    console.log('Note: contact.php will not execute — no PHP locally.');
});
