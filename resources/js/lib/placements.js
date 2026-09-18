export function clampPlacement(element) {
    const width = Math.max(0.005, Math.min(1, element.width_ratio));
    const height = Math.max(0.005, Math.min(1, element.height_ratio));
    return { ...element, width_ratio: width, height_ratio: height, x_ratio: Math.max(0, Math.min(1 - width, element.x_ratio)), y_ratio: Math.max(0, Math.min(1 - height, element.y_ratio)) };
}

// Invert PDF.js's viewport transform, including CropBox, rotation and UserUnit.
export function pdfRectangle(element, viewport) {
    const left = element.x_ratio * viewport.width;
    const bottom = (element.y_ratio + element.height_ratio) * viewport.height;
    const [x, y] = viewport.convertToPdfPoint(left, bottom);
    const [rightX, rightY] = viewport.convertToPdfPoint(left + element.width_ratio * viewport.width, bottom);
    const [topX, topY] = viewport.convertToPdfPoint(left, bottom - element.height_ratio * viewport.height);
    return { x, y, width: Math.hypot(rightX - x, rightY - y), height: Math.hypot(topX - x, topY - y), angle: Math.atan2(rightY - y, rightX - x) * 180 / Math.PI };
}
