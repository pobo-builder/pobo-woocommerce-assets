#!/bin/bash
#
# Deploy script for Pobo Page Builder - WooCommerce
# Usage: ./bin/deploy.sh [version]
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Plugin settings
PLUGIN_SLUG="pobo-page-builder-woocommerce"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${PLUGIN_DIR}/build"
DIST_DIR="${BUILD_DIR}/dist"
SVN_DIR="${BUILD_DIR}/svn"

# Get version from main plugin file
VERSION=$(grep -m1 "Version:" "${PLUGIN_DIR}/pobo-woocommerce-assets.php" | awk '{print $3}')

# Allow version override
if [ -n "$1" ]; then
    VERSION="$1"
fi

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Deploying ${PLUGIN_SLUG} v${VERSION}${NC}"
echo -e "${GREEN}========================================${NC}"

# Clean build directory
echo -e "${YELLOW}Cleaning build directory...${NC}"
rm -rf "${BUILD_DIR}"
mkdir -p "${DIST_DIR}/${PLUGIN_SLUG}"

# Files to exclude from distribution
EXCLUDE_FILES=(
    ".git"
    ".gitignore"
    ".idea"
    ".claude"
    ".wp-env.json"
    ".distignore"
    "bin"
    "build"
    "node_modules"
    "tests"
    "*.log"
    ".DS_Store"
)

# Build exclude arguments
EXCLUDE_ARGS=""
for file in "${EXCLUDE_FILES[@]}"; do
    EXCLUDE_ARGS="${EXCLUDE_ARGS} --exclude=${file}"
done

# Copy files to dist
echo -e "${YELLOW}Copying files...${NC}"
rsync -av ${EXCLUDE_ARGS} "${PLUGIN_DIR}/" "${DIST_DIR}/${PLUGIN_SLUG}/"

# Create ZIP file
echo -e "${YELLOW}Creating ZIP archive...${NC}"
cd "${DIST_DIR}"
zip -r "${BUILD_DIR}/${PLUGIN_SLUG}-${VERSION}.zip" "${PLUGIN_SLUG}"

echo -e "${GREEN}----------------------------------------${NC}"
echo -e "${GREEN}ZIP file created:${NC}"
echo -e "${BUILD_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"
echo -e "${GREEN}----------------------------------------${NC}"

# SVN deploy (optional)
read -p "Deploy to WordPress.org SVN? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    # Check for SVN credentials
    if [ -z "$WP_ORG_USERNAME" ]; then
        read -p "WordPress.org username: " WP_ORG_USERNAME
    fi

    SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}"

    echo -e "${YELLOW}Checking out SVN repository...${NC}"
    svn co "${SVN_URL}" "${SVN_DIR}" --depth immediates
    svn update --set-depth infinity "${SVN_DIR}/trunk"
    svn update --set-depth infinity "${SVN_DIR}/assets"

    # Clear trunk
    echo -e "${YELLOW}Updating trunk...${NC}"
    rm -rf "${SVN_DIR}/trunk/"*
    cp -r "${DIST_DIR}/${PLUGIN_SLUG}/"* "${SVN_DIR}/trunk/"

    # Add new files and remove deleted
    cd "${SVN_DIR}"
    svn add --force trunk/* 2>/dev/null || true
    svn status trunk | grep '^\!' | awk '{print $2}' | xargs -I {} svn rm {} 2>/dev/null || true

    # Create tag
    echo -e "${YELLOW}Creating tag ${VERSION}...${NC}"
    svn update --set-depth empty "${SVN_DIR}/tags"
    if svn ls "${SVN_URL}/tags/${VERSION}" 2>/dev/null; then
        echo -e "${RED}Tag ${VERSION} already exists!${NC}"
        exit 1
    fi
    svn cp trunk "tags/${VERSION}"

    # Show changes
    echo -e "${YELLOW}Changes to commit:${NC}"
    svn status

    read -p "Commit changes? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        svn ci -m "Release ${VERSION}" --username "${WP_ORG_USERNAME}"
        echo -e "${GREEN}Successfully deployed v${VERSION} to WordPress.org!${NC}"
    else
        echo -e "${YELLOW}Commit cancelled. SVN working copy is in: ${SVN_DIR}${NC}"
    fi
else
    echo -e "${YELLOW}SVN deploy skipped.${NC}"
fi

echo -e "${GREEN}Done!${NC}"
