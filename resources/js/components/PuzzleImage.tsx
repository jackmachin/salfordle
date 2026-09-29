import { useEffect, useState } from 'react';
import { api } from '../api';

// Each fetch is a billable Street View request, so share one per URL: React's
// StrictMode runs effects twice in development, and remounts shouldn't refetch.
const requests = new Map<string, Promise<string>>();

function load(url: string): Promise<string> {
    if (!requests.has(url)) {
        requests.set(url, api.image(url));
    }

    return requests.get(url)!;
}

export default function PuzzleImage({ url }: { url: string }) {
    const [src, setSrc] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let current = true;

        load(url)
            .then((blobUrl) => current && setSrc(blobUrl))
            .catch((e: Error) => current && setError(e.message));

        return () => {
            current = false;
        };
    }, [url]);

    return (
        <div className="relative aspect-[8/5] w-full overflow-hidden rounded-xl bg-stone-300 shadow-sm">
            {src && <img src={src} alt="Street View image of today's mystery street" className="h-full w-full object-cover" />}
            {!src && (
                <p className="absolute inset-0 flex items-center justify-center p-6 text-center text-stone-600">
                    {error ?? 'Loading image…'}
                </p>
            )}
        </div>
    );
}
