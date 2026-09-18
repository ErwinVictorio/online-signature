import { useEffect, useRef, useState } from 'react';
import PdfPage from './PdfPage';
export default function PageThumbnail({ pdf, pageNumber, selected, onClick }) {
    const ref = useRef(null), [visible, setVisible] = useState(false);
    useEffect(() => {
        const observer = new IntersectionObserver(entries => { if (entries[0].isIntersecting) { setVisible(true); observer.disconnect(); } }, { rootMargin: '200px' });
        observer.observe(ref.current);
        return () => observer.disconnect();
    }, []);
    return <button ref={ref} aria-label={`Go to page ${pageNumber}`} aria-current={selected ? 'page' : undefined} className={`shrink-0 p-1 ${selected ? 'ring-2 ring-indigo-500' : ''}`} onClick={onClick}>{visible ? <PdfPage pdf={pdf} pageNumber={pageNumber} thumbnail /> : <div className="h-32 w-[100px] bg-white" />}<span className="text-xs">Page {pageNumber}</span></button>;
}
