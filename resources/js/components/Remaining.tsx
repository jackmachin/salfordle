type Props = { remaining: number; total: number; used: number; max: number; playing: boolean };

export default function Remaining({ remaining, total, used, max, playing }: Props) {
    // Log scale, so the bar keeps visibly shrinking as the pool goes from thousands to a handful.
    const width = remaining <= 1 ? 1 : Math.log(remaining) / Math.log(total);

    return (
        <div className="rounded-xl bg-white px-4 py-3 shadow-sm">
            <div className="flex items-baseline justify-between gap-4 text-sm">
                <p>
                    <span className="text-2xl font-bold tabular-nums">{remaining.toLocaleString('en-GB')}</span>{' '}
                    <span className="text-stone-500">
                        of {total.toLocaleString('en-GB')} streets {remaining === 1 ? 'left' : 'could be the answer'}
                    </span>
                </p>
                <p className="shrink-0 text-stone-500 tabular-nums">
                    {playing ? `Guess ${used + 1}/${max}` : `${used}/${max} guesses`}
                </p>
            </div>
            <div className="mt-2 h-2 overflow-hidden rounded-full bg-stone-200">
                <div
                    className="h-full rounded-full bg-emerald-600 transition-[width] duration-500"
                    style={{ width: `${Math.max(width * 100, 1)}%` }}
                />
            </div>
        </div>
    );
}
