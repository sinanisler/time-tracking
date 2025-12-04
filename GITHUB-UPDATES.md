# GitHub Auto-Update System Documentation

## Overview

Your Time Tracking plugin now has a solid GitHub-based auto-update system that automatically detects and installs updates from GitHub releases.

## How It Works

### For WordPress Users
- WordPress automatically checks for updates from your GitHub repository
- When a new version is released, users see an update notification in their WordPress admin
- Users can click "Update Now" to automatically download and install the latest version
- The update process is seamless and works just like WordPress.org plugin updates

### For Developers (You)

#### Creating Releases

The GitHub Actions workflow automatically creates releases when you push commits with specific keywords:

**Semantic Versioning:**
- `release:major` - Bumps major version (3.9 → 4.0.0)
- `release:minor` - Bumps minor version (3.9 → 3.10.0)  
- `release:patch` or `release` - Bumps patch version (3.9 → 3.9.1)

**Example Commit Messages:**
```bash
git commit -m "release:patch - Fixed calendar display bug"
git commit -m "release:minor - Added new export feature"
git commit -m "release:major - Complete UI redesign"
```

#### What Happens Automatically

1. **Version Detection** - Reads current version from `time-tracking.php`
2. **Version Bump** - Calculates new version based on your commit message
3. **Duplicate Check** - Prevents creating duplicate releases
4. **File Updates** - Updates both the plugin header AND the version constant
5. **Changelog Generation** - Creates changelog from all commits since last release
6. **Zip Creation** - Creates `time-tracking.zip` with all plugin files
7. **GitHub Release** - Creates a GitHub release with the zip file attached
8. **Auto-Update** - WordPress sites can now see and install the update

## Version Consistency

Both version locations are now kept in sync:
- Plugin header: `Version: 3.9`
- PHP constant: `define( 'TIME_TRACKING_VERSION', '3.9' )`

The GitHub Actions workflow automatically updates BOTH when creating a release.

## Testing the Update System

### Manual Test

1. **Create a test release:**
   ```bash
   git commit -m "release:patch - Test release" --allow-empty
   git push
   ```

2. **Check GitHub Actions:**
   - Go to your repository → Actions tab
   - Watch the workflow run
   - Verify it creates a release

3. **Test on WordPress:**
   - Go to WordPress Admin → Plugins
   - Click "Check for updates"
   - You should see an update available
   - Click "Update Now" to test the installation

### Verify Version

After updating, check that the version number is correct:
- WordPress Plugins page should show the new version
- In the plugin files, both the header and constant should match

## Troubleshooting

### No Update Appearing in WordPress

1. **Force WordPress to check for updates:**
   - Go to Dashboard → Updates
   - Click "Check Again"

2. **Clear WordPress transients:**
   ```php
   delete_site_transient('update_plugins');
   ```

3. **Check GitHub release:**
   - Verify the release exists on GitHub
   - Verify `time-tracking.zip` is attached to the release

### Version Mismatch

If versions don't match, the workflow will now sync them automatically on the next release.

### Duplicate Release Error

If you see "Tag already exists", it means that version was already released. Use a different bump type or manually increment the version.

## Current Status

✅ **Fixed Issues:**
- Version constant now matches plugin header (3.9)
- Both version locations updated automatically on release
- Semantic versioning implemented (major.minor.patch)
- Automatic changelog generation from commits
- Duplicate release prevention
- Proper error handling and validation

✅ **Your Update System is Now Solid!**

The plugin will automatically notify WordPress users when updates are available, and they can install them with one click.
