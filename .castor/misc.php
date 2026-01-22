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
    run("cp -rf icon.svg ../api/public/icon.svg", context: $context);
    run("cp -rf icon.svg ../extension/icons/icon.svg", context: $context);

    $projectRoot = dirname(__DIR__);
    $svgFilePath = $projectRoot . '/images/icon.svg';
    $docsIconPath = $projectRoot . '/docs/src/assets/icon.svg';

    $svgContent = file_get_contents($svgFilePath);
    if ($svgContent === false) {
        throw new \RuntimeException("Failed to read {$svgFilePath}");
    }

    $svgCentered = centerSvgOnCanvas($svgContent, 750, 148);

    if (file_put_contents($docsIconPath, $svgCentered) === false) {
        throw new \RuntimeException("Failed to write {$docsIconPath}");
    }

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
 * Parses and validates the viewBox from SVG content
 *
 * @param string $svgContent The SVG content as a string
 * @return array{x: float, y: float, width: float, height: float} The parsed viewBox values
 * @throws \RuntimeException If viewBox is not found or invalid
 */
function parseViewBox(string $svgContent): array
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

    return ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];
}

/**
 * Replaces the viewBox in SVG content
 *
 * @param string $svgContent The SVG content as a string
 * @param float $x The new viewBox x coordinate
 * @param float $y The new viewBox y coordinate
 * @param float $width The new viewBox width
 * @param float $height The new viewBox height
 * @return string The modified SVG content
 */
function replaceViewBox(string $svgContent, float $x, float $y, float $width, float $height): string
{
    $newViewBox = sprintf('%.6g %.6g %.6g %.6g', $x, $y, $width, $height);
    return preg_replace('/viewBox=["\']([^"\']+)["\']/', "viewBox=\"{$newViewBox}\"", $svgContent);
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
    ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height] = parseViewBox($svgContent);

    $paddingX = $width * $paddingRatio;
    $paddingY = $height * $paddingRatio;

    $newX = $x - $paddingX;
    $newY = $y - $paddingY;
    $newWidth = $width + (2 * $paddingX);
    $newHeight = $height + (2 * $paddingY);

    return replaceViewBox($svgContent, $newX, $newY, $newWidth, $newHeight);
}

/**
 * Centers an SVG on a canvas of specified dimensions by adjusting the viewBox
 *
 * @param string $svgContent The SVG content as a string
 * @param float $canvasWidth The target canvas width in pixels
 * @param float $canvasHeight The target canvas height in pixels
 * @return string The modified SVG content
 */
function centerSvgOnCanvas(string $svgContent, float $canvasWidth, float $canvasHeight): string
{
    ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height] = parseViewBox($svgContent);

    $canvasAspectRatio = $canvasWidth / $canvasHeight;

    // Calculate the viewBox dimensions that will exactly match the canvas aspect ratio
    // The viewBox must have the same aspect ratio as width/height for proper scaling
    if ($width / $height > $canvasAspectRatio) {
        // SVG is wider relative to canvas - fit by width, extend height
        $viewBoxWidth = $width;
        $viewBoxHeight = $width / $canvasAspectRatio;
    } else {
        // SVG is taller relative to canvas - fit by height, extend width
        $viewBoxHeight = $height;
        $viewBoxWidth = $height * $canvasAspectRatio;
    }

    // Center the original SVG content within the new viewBox
    $offsetX = ($viewBoxWidth - $width) / 2;
    $offsetY = ($viewBoxHeight - $height) / 2;

    $newX = $x - $offsetX;
    $newY = $y - $offsetY;
    $newWidth = $viewBoxWidth;
    $newHeight = $viewBoxHeight;

    $modifiedSvg = replaceViewBox($svgContent, $newX, $newY, $newWidth, $newHeight);

    $modifiedSvg = preg_replace('/width=["\'][^"\']+["\']/', "width=\"{$canvasWidth}\"", $modifiedSvg);
    $modifiedSvg = preg_replace('/height=["\'][^"\']+["\']/', "height=\"{$canvasHeight}\"", $modifiedSvg);

    return $modifiedSvg;
}
