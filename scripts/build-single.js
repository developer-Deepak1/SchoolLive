const fs = require('fs');
const path = require('path');
const esbuild = require('esbuild');

(async function(){
  const distDir = path.resolve(__dirname, '../dist/sakai-ng/browser');
  const outFile = path.resolve(__dirname, '../dist/sakai-ng/single.html');
  if (!fs.existsSync(distDir)) {
    console.error('dist directory not found:', distDir);
    process.exit(1);
  }

  // Find entry files - prefer main-*.js
  const files = fs.readdirSync(distDir);
  const mainJs = files.find(f => /^main.*\.js$/.test(f));
  const polyfills = files.find(f => /^polyfills.*\.js$/.test(f));
  const chunks = files.filter(f => /^chunk-.*\.js$/.test(f));
  const styles = files.find(f => /^styles.*\.css$/.test(f));
  const indexHtml = path.join(distDir, 'index.html');

  if (!mainJs || !fs.existsSync(path.join(distDir, mainJs))) {
    console.error('main JS not found in', distDir);
    process.exit(1);
  }

  // Build single JS bundle using esbuild. When bundling multiple input files, esbuild
  // requires an outdir, or we can provide a single virtual entry via stdin that imports
  // the desired files. We'll generate an import list so esbuild resolves and bundles
  // everything into one output file.
  const importLines = [];
  if (polyfills) importLines.push(`import './${polyfills}';`);
  // include chunks (order isn't always critical thanks to module imports)
  chunks.forEach(c => importLines.push(`import './${c}';`));
  importLines.push(`import './${mainJs}';`);

  const virtualEntry = importLines.join('\n');
  const tmpOut = path.join(distDir, 'bundle-temp.js');
  try {
    await esbuild.build({
      stdin: {
        contents: virtualEntry,
        resolveDir: distDir,
        sourcefile: 'virtual-entry.js'
      },
      bundle: true,
      outfile: tmpOut,
      minify: true,
      platform: 'browser',
      target: ['es2017']
    });
  } catch (e) {
    console.error('esbuild failed:', e.message || e);
    process.exit(1);
  }

  const jsContent = fs.readFileSync(tmpOut, 'utf8');
  fs.unlinkSync(tmpOut);

  // Read CSS
  let cssContent = '';
  if (styles && fs.existsSync(path.join(distDir, styles))) {
    cssContent = fs.readFileSync(path.join(distDir, styles), 'utf8');
  }

  // Read index.html template
  let htmlTpl = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
  if (cssContent) htmlTpl += '<style>' + cssContent + '</style>';
  htmlTpl += '</head><body><app-root></app-root>';
  htmlTpl += '<script>' + jsContent + '</script>';
  htmlTpl += '</body></html>';

  fs.writeFileSync(outFile, htmlTpl, 'utf8');
  console.log('Wrote', outFile);
})();
