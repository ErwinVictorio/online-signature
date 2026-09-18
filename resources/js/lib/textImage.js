// Use the same raster for preview and export: names in any browser-supported script
// retain their appearance without depending on the PDF's installed fonts.
export function textImage(element) {
    const canvas = document.createElement('canvas');
    canvas.width = 1200; canvas.height = 180;
    const context = canvas.getContext('2d');
    let size = 120;
    context.font = `${size}px Arial, sans-serif`;
    const measured = context.measureText(element.text || '').width;
    if (measured > 1160) size *= 1160 / measured;
    context.font = `${size}px Arial, sans-serif`;
    context.fillStyle = '#111827'; context.textBaseline = 'middle';
    context.fillText(element.text || '', 8, 90);
    return canvas.toDataURL('image/png');
}
