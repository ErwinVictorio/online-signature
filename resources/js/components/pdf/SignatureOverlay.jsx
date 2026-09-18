import { Rnd } from 'react-rnd';
import { clampPlacement } from '@/lib/placements';
export default function SignatureOverlay({ element, viewport, src, selected, onSelect, onChange, disabled }) {
    const width = element.width_ratio * viewport.width, height = element.height_ratio * viewport.height;
    const update = (position, size = { width, height }) => onChange(clampPlacement({ ...element, x_ratio: position.x / viewport.width, y_ratio: position.y / viewport.height, width_ratio: size.width / viewport.width, height_ratio: size.height / viewport.height }));
    return <Rnd bounds="parent" size={{ width, height }} position={{ x: element.x_ratio * viewport.width, y: element.y_ratio * viewport.height }} minWidth={8} minHeight={8} lockAspectRatio={width / height} disableDragging={disabled} enableResizing={!disabled && selected ? { bottomRight: true, bottomLeft: true, topRight: true, topLeft: true } : false} onMouseDown={onSelect} onTouchStart={onSelect} onDragStop={(_, position) => update(position)} onResizeStop={(_, __, ref, ___, position) => update(position, { width: parseFloat(ref.style.width), height: parseFloat(ref.style.height) })} className={selected ? 'outline-2 outline-indigo-500' : 'hover:outline hover:outline-indigo-300'}>
        <div role="button" tabIndex={disabled ? -1 : 0} aria-label={`Select ${element.type}`} onFocus={onSelect} className="h-full w-full cursor-move touch-none" onKeyDown={e => { if (disabled || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) return; e.preventDefault(); update({ x: element.x_ratio * viewport.width + (e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0) * (e.shiftKey ? 10 : 1), y: element.y_ratio * viewport.height + (e.key === 'ArrowDown' ? 1 : e.key === 'ArrowUp' ? -1 : 0) * (e.shiftKey ? 10 : 1) }); }}>
            {src ? <img draggable={false} src={src} alt={element.type} className="pointer-events-none h-full w-full select-none" /> : <span className="block h-full bg-red-50 text-xs text-red-700">Signature unavailable</span>}
        </div>
    </Rnd>;
}
