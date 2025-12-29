<?php /** @noinspection PhpUnused */

namespace misc;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Copy global assets to each project')]
function copy_assets(): void
{
    io()->title('Copying global assets to each project');

    $context = context()->withWorkingDirectory('./images');
    run("cp -rfv icon.svg ../api/public/icon.svg", context: $context);
    run("cp -rfv icon.svg ../extension/icons/icon.svg", context: $context);

    // Get project root directory (parent of .castor)
    $projectRoot = dirname(__DIR__);
    $svgFilePath = $projectRoot . '/images/icon.svg';
    $clientIconPath = $projectRoot . '/client/public/icon.svg';

    $svgContent = file_get_contents($svgFilePath);
    if ($svgContent === false) {
        throw new \RuntimeException("Failed to read {$svgFilePath}");
    }

    $svgWithPadding = addSvgPadding($svgContent, 0.1);

    if (file_put_contents($clientIconPath, $svgWithPadding) === false) {
        throw new \RuntimeException("Failed to write {$clientIconPath}");
    }
}

/**
 * Adds padding to an SVG by adjusting the viewBox
 *
 * @param string $svgContent The SVG content as a string
 * @param float $paddingRatio The padding ratio (e.g., 0.1 for 10%)
 * @return string The modified SVG content
 */
function addSvgPadding(string $svgContent, float $paddingRatio): string
{
    if (!preg_match('/viewBox=["\']([^"\']+)["\']/', $svgContent, $matches)) {
        throw new \RuntimeException("No viewBox found");
    }

    $viewBox = $matches[1];
    $viewBoxParts = array_map('floatval', preg_split('/\s+/', trim($viewBox)));

    if (count($viewBoxParts) !== 4) {
        throw new \RuntimeException("Invalid viewBox");
    }

    [$x, $y, $width, $height] = $viewBoxParts;

    $paddingX = $width * $paddingRatio;
    $paddingY = $height * $paddingRatio;

    $newX = $x - $paddingX;
    $newY = $y - $paddingY;
    $newWidth = $width + (2 * $paddingX);
    $newHeight = $height + (2 * $paddingY);

    $newViewBox = sprintf('%.6g %.6g %.6g %.6g', $newX, $newY, $newWidth, $newHeight);

    // Replace the viewBox in the SVG content
    $modifiedSvg = preg_replace('/viewBox=["\']([^"\']+)["\']/', "viewBox=\"{$newViewBox}\"", $svgContent);

    return $modifiedSvg;
}
