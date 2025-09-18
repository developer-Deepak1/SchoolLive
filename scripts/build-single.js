const fs = require('fs');
const path = require('path');
const esbuild = require('esbuild');

(async function(){
  const distDir = path.resolve(__dirname, '../dist/sakai-ng/browser');
  const outDir = path.resolve(__dirname, '../dist/sakai-ng/single');
  
  // Generate cache buster timestamp
  const cacheBuster = Date.now();
  console.log('Cache buster timestamp:', cacheBuster);
  
  if (!fs.existsSync(distDir)) {
    console.error('dist directory not found:', distDir);
    process.exit(1);
  }

  // Ensure output dir
  if (fs.existsSync(outDir)) {
    fs.rmSync(outDir, { recursive: true, force: true });
  }
  fs.mkdirSync(outDir, { recursive: true });

  // Find entry files - prefer the most recently modified matching files
  const files = fs.readdirSync(distDir);
  function newest(matchRegex) {
    const candidates = files.filter(f => matchRegex.test(f));
    if (!candidates || candidates.length === 0) return null;
    candidates.sort((a, b) => {
      const sa = fs.statSync(path.join(distDir, a)).mtimeMs;
      const sb = fs.statSync(path.join(distDir, b)).mtimeMs;
      return sb - sa; // newest first
    });
    return candidates[0];
  }

  const mainJs = newest(/^main.*\.js$/);
  const polyfills = newest(/^polyfills.*\.js$/);
  // for chunks include all chunk-* files but sort newest-first when bundling order matters
  let chunks = files.filter(f => /^chunk-.*\.js$/.test(f));
  chunks.sort((a, b) => fs.statSync(path.join(distDir, b)).mtimeMs - fs.statSync(path.join(distDir, a)).mtimeMs);
  const styles = newest(/^styles.*\.css$/);
  const indexHtmlPath = path.join(distDir, 'index.html');

  if (!mainJs || !fs.existsSync(path.join(distDir, mainJs))) {
    console.error('main JS not found in', distDir);
    process.exit(1);
  }

  console.log('Using files:');
  console.log('  main:', mainJs);
  if (polyfills) console.log('  polyfills:', polyfills);
  console.log('  styles:', styles);
  if (chunks && chunks.length) console.log('  chunks (count):', chunks.length);

  // Build single JS bundle using esbuild. We'll create a virtual entry that imports
  // polyfills, chunks and main, then write bundle to outDir as `app.bundle.js`.
  const importLines = [];
  if (polyfills) importLines.push(`import './${polyfills}';`);
  chunks.forEach(c => importLines.push(`import './${c}';`));
  importLines.push(`import './${mainJs}';`);

  const virtualEntry = importLines.join('\n');
  const bundleOut = path.join(outDir, `app.bundle.${cacheBuster}.js`);
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
    fs.writeFileSync(path.join(outDir, `style.${cacheBuster}.css`), cssContent, 'utf8');
  } else {
    // create an empty style.css for consistency
    fs.writeFileSync(path.join(outDir, `style.${cacheBuster}.css`), '', 'utf8');
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

  // Build index.html using `src/index.html` if available, else fallback to dist index.html or minimal template.
  const srcIndexPath = path.resolve(__dirname, '../src/index.html');
  let template = null;
  if (fs.existsSync(srcIndexPath)) {
    template = fs.readFileSync(srcIndexPath, 'utf8');
  } else if (fs.existsSync(indexHtmlPath)) {
    template = fs.readFileSync(indexHtmlPath, 'utf8');
  }

  let finalHtml;
  if (template) {
    // Insert stylesheet link before closing </head> and script before closing </body>
    finalHtml = template.replace(/<\/head>/i, `  <link rel="stylesheet" href="style.${cacheBuster}.css">\n</head>`);
    finalHtml = finalHtml.replace(/<\/body>/i, `  <script src="app.bundle.${cacheBuster}.js" defer></script>\n</body>`);
  } else {
    // fallback minimal template
    finalHtml = `<!doctype html>\n<html>\n  <head>\n    <meta charset="utf-8" />\n    <meta name="viewport" content="width=device-width, initial-scale=1" />\n    <base href="/">\n    <!-- Prevent HTML caching -->\n    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">\n    <meta http-equiv="Pragma" content="no-cache">\n    <meta http-equiv="Expires" content="0">\n    <link rel="stylesheet" href="style.${cacheBuster}.css">\n  </head>\n  <body>\n    <app-root></app-root>\n    <script src="app.bundle.${cacheBuster}.js" defer></script>\n  </body>\n</html>`;
  }

  fs.writeFileSync(path.join(outDir, 'index.html'), finalHtml, 'utf8');
  console.log('Wrote single-folder to', outDir);
  console.log('Generated files:');
  console.log(`  - app.bundle.${cacheBuster}.js`);
  console.log(`  - style.${cacheBuster}.css`);
  console.log(`  - index.html`);
})();
