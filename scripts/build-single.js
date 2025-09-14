const fs = require('fs');
const path = require('path');
const esbuild = require('esbuild');

(async function(){
  const distDir = path.resolve(__dirname, '../dist/sakai-ng/browser');
  const outDir = path.resolve(__dirname, '../dist/sakai-ng/single');
  if (!fs.existsSync(distDir)) {
    console.error('dist directory not found:', distDir);
    process.exit(1);
  }

  // Ensure output dir
  if (fs.existsSync(outDir)) {
    fs.rmSync(outDir, { recursive: true, force: true });
  }
  fs.mkdirSync(outDir, { recursive: true });

  // Find entry files - prefer main-*.js
  const files = fs.readdirSync(distDir);
  const mainJs = files.find(f => /^main.*\.js$/.test(f));
  const polyfills = files.find(f => /^polyfills.*\.js$/.test(f));
  const chunks = files.filter(f => /^chunk-.*\.js$/.test(f));
  const styles = files.find(f => /^styles.*\.css$/.test(f));
  const indexHtmlPath = path.join(distDir, 'index.html');

  if (!mainJs || !fs.existsSync(path.join(distDir, mainJs))) {
    console.error('main JS not found in', distDir);
    process.exit(1);
  }

  // Build single JS bundle using esbuild. We'll create a virtual entry that imports
  // polyfills, chunks and main, then write bundle to outDir as `app.bundle.js`.
  const importLines = [];
  if (polyfills) importLines.push(`import './${polyfills}';`);
  chunks.forEach(c => importLines.push(`import './${c}';`));
  importLines.push(`import './${mainJs}';`);

  const virtualEntry = importLines.join('\n');
  const bundleOut = path.join(outDir, 'app.bundle.js');
  try {
    await esbuild.build({
      stdin: {
        contents: virtualEntry,
        resolveDir: distDir,
        sourcefile: 'virtual-entry.js'
      },
      bundle: true,
      outfile: bundleOut,
      minify: true,
      platform: 'browser',
      target: ['es2017']
    });
  } catch (e) {
    console.error('esbuild failed:', e.message || e);
    process.exit(1);
  }

  // Write CSS to a separate file named style.css in outDir.
  let cssContent = '';
  if (styles && fs.existsSync(path.join(distDir, styles))) {
    cssContent = fs.readFileSync(path.join(distDir, styles), 'utf8');
    // Convert absolute asset paths to relative so they work from the single folder.
    cssContent = cssContent.replace(/url\((['\"]?)\/assets\//g, 'url($1assets/');
    cssContent = cssContent.replace(/url\((['\"]?)assets\//g, 'url($1assets/');
    // Ensure media paths remain relative (./media/, /media/, media/)
    cssContent = cssContent.replace(/url\((['\"]?)\.?\/media\//g, 'url($1media/');
    cssContent = cssContent.replace(/url\((['\"]?)\/media\//g, 'url($1media/');
    cssContent = cssContent.replace(/url\((['\"]?)media\//g, 'url($1media/');
    fs.writeFileSync(path.join(outDir, 'style.css'), cssContent, 'utf8');
  } else {
    // create an empty style.css for consistency
    fs.writeFileSync(path.join(outDir, 'style.css'), '', 'utf8');
  }

  // Copy assets and media folders (if present) so media referenced by CSS/HTML is available.
  const assetsSrc = path.join(distDir, 'assets');
  const assetsDest = path.join(outDir, 'assets');
  const mediaSrc = path.join(distDir, 'media');
  const mediaDest = path.join(outDir, 'media');
  function copyDirSync(src, dest) {
    if (!fs.existsSync(src)) return;
    fs.mkdirSync(dest, { recursive: true });
    const items = fs.readdirSync(src);
    for (const it of items) {
      const s = path.join(src, it);
      const d = path.join(dest, it);
      const stat = fs.statSync(s);
      if (stat.isDirectory()) copyDirSync(s, d);
      else fs.copyFileSync(s, d);
    }
  }
  if (fs.existsSync(assetsSrc)) {
    copyDirSync(assetsSrc, assetsDest);
  }
  if (fs.existsSync(mediaSrc)) {
    copyDirSync(mediaSrc, mediaDest);
  }

  // Build a minimal index.html referencing the produced files. Preserve <base href> if present.
  let baseHref = '/';
  if (fs.existsSync(indexHtmlPath)) {
    try {
      const original = fs.readFileSync(indexHtmlPath, 'utf8');
      const m = original.match(/<base href=\"([^\"]*)\">/i);
      if (m && m[1]) baseHref = m[1];
    } catch (e) {
      // ignore
    }
  }

  const indexHtml = `<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <base href="${baseHref}">
    <link rel="stylesheet" href="style.css">
  </head>
  <body>
    <app-root></app-root>
    <script src="app.bundle.js" defer></script>
  </body>
</html>`;

  fs.writeFileSync(path.join(outDir, 'index.html'), indexHtml, 'utf8');
  console.log('Wrote single-folder to', outDir);
})();
