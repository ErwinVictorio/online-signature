import { Link } from '@inertiajs/react';
export default function Pagination({ data }) {
    return <div className="mt-5 flex items-center justify-between text-sm"><span className="text-slate-500">{data.total} results · Page {data.current_page} of {data.last_page}</span><div className="flex gap-4">{data.prev_page_url && <Link href={data.prev_page_url}>Previous</Link>}{data.next_page_url && <Link href={data.next_page_url}>Next</Link>}</div></div>;
}
