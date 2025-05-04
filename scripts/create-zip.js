/**
 * Script to create a production-ready zip file from the dist directory
 */
const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

const SOURCE_DIR = path.resolve(__dirname, '..');
const DIST_DIR = path.resolve(SOURCE_DIR, 'dist');
const PLUGIN_NAME = 'perfectcopy';

// Function to create zip file
const createZip = () => {
  console.log('Creating production zip file...');
  
  // Create output directory if it doesn't exist
  const outputDir = path.resolve(SOURCE_DIR, 'production');
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir);
  }
  
  // Get package version from package.json
  const packageJson = require(path.join(SOURCE_DIR, 'package.json'));
  const version = packageJson.version || '1.0.0';
  
  // Create a file to stream archive data to
  const outputPath = path.join(outputDir, `${PLUGIN_NAME}-${version}.zip`);
  const output = fs.createWriteStream(outputPath);
  const archive = archiver('zip', {
    zlib: { level: 9 } // Maximum compression level
  });
  
  // Listen for warnings
  archive.on('warning', (err) => {
    if (err.code === 'ENOENT') {
      console.warn('Warning:', err);
    } else {
      throw err;
    }
  });
  
  // Listen for errors
  archive.on('error', (err) => {
    throw err;
  });
  
  // Pipe archive data to the file
  archive.pipe(output);
  
  // Add entire dist directory to the archive
  // This will place files at the root of the zip
  archive.directory(DIST_DIR, PLUGIN_NAME);
  
  // Finalize the archive
  archive.finalize();
  
  // When the output stream is closed, we're done
  output.on('close', () => {
    const fileSize = (archive.pointer() / 1024 / 1024).toFixed(2);
    console.log(`Production zip created successfully: ${outputPath}`);
    console.log(`Total size: ${fileSize} MB`);
  });
};

// Execute
createZip();
