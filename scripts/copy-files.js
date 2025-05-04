/**
 * Script to copy plugin files to the dist directory
 * Excludes development files like PRD.md
 */
const fs = require('fs-extra');
const path = require('path');

const SOURCE_DIR = path.resolve(__dirname, '..');
const DIST_DIR = path.resolve(SOURCE_DIR, 'dist');

// Files/directories to include
const INCLUDES = [
  'includes',
  'languages',
  'assets',  // Include entire assets folder for structure
  'index.php',
  'perfectcopy.php',
  'readme.txt'
];

// Create index.php files for directories
const createIndexPhp = () => {
  const directories = [
    'assets/css',
    'assets/js',
    'assets',
    ''  // root dist directory
  ];
  
  directories.forEach(dir => {
    const targetPath = path.join(DIST_DIR, dir);
    if (fs.existsSync(targetPath)) {
      fs.writeFileSync(
        path.join(targetPath, 'index.php'),
        '<?php // Silence is golden.'
      );
    }
  });
};

// Copy placeholder index.php files
const copyIndexPhpFiles = () => {
  const indexPhpPaths = [
    'assets/index.php',
    'assets/css/index.php',
    'assets/js/index.php'
  ];
  
  indexPhpPaths.forEach(filePath => {
    const sourcePath = path.join(SOURCE_DIR, filePath);
    if (fs.existsSync(sourcePath)) {
      const targetDir = path.dirname(path.join(DIST_DIR, filePath));
      fs.ensureDirSync(targetDir);
      fs.copyFileSync(sourcePath, path.join(DIST_DIR, filePath));
    }
  });
};

// Main copy function
const copyFiles = () => {
  console.log('Copying plugin files to dist directory...');
  
  // Create dist directory if it doesn't exist
  fs.ensureDirSync(DIST_DIR);
  
  // Copy each included file/directory
  INCLUDES.forEach(item => {
    const sourcePath = path.join(SOURCE_DIR, item);
    const targetPath = path.join(DIST_DIR, item);
    
    if (fs.existsSync(sourcePath)) {
      if (fs.lstatSync(sourcePath).isDirectory()) {
        fs.copySync(sourcePath, targetPath, {
          filter: (src) => {
            // Exclude any hidden files or directories
            const basename = path.basename(src);
            return !basename.startsWith('.');
          }
        });
      } else {
        fs.copySync(sourcePath, targetPath);
      }
      console.log(`Copied: ${item}`);
    } else {
      console.warn(`Warning: ${item} does not exist`);
    }
  });
  
  // Copy necessary index.php files
  copyIndexPhpFiles();
  
  // Create any missing index.php files
  createIndexPhp();
  
  // Clean up duplicate CSS and JS directories
  cleanupDuplicateFiles();
  
  console.log('File copying complete!');
};

// Clean up duplicate CSS and JS directories
const cleanupDuplicateFiles = () => {
  console.log('Cleaning up duplicate files...');
  
  // Remove the dist/css and dist/js directories since we now use assets/css and assets/js
  if (fs.existsSync(path.join(DIST_DIR, 'css'))) {
    fs.removeSync(path.join(DIST_DIR, 'css'));
    console.log('Removed: dist/css directory');
  }
  
  if (fs.existsSync(path.join(DIST_DIR, 'js'))) {
    fs.removeSync(path.join(DIST_DIR, 'js'));
    console.log('Removed: dist/js directory');
  }
};

// Execute
copyFiles();
