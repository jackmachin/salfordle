import type { Guess } from '../api';

export const ARROWS: Record<string, string> = {
    N: '⬆️',
    NE: '↗️',
    E: '➡️',
    SE: '↘️',
    S: '⬇️',
    SW: '↙️',
    W: '⬅️',
    NW: '↖️',
};

const COLUMNS = ['Ward', 'Postcode', 'Type', 'Letter', 'Distance'];

export default function GuessGrid({ guesses, max }: { guesses: Guess[]; max: number }) {
    const empty = Math.max(0, max - guesses.length);

    return (
        <section aria-label="Your guesses" className="flex flex-col gap-2">
            <div className="grid grid-cols-5 gap-1.5 px-0.5 text-center text-[11px] font-semibold tracking-wide text-stone-500 uppercase">
                {COLUMNS.map((c) => (
                    <div key={c}>{c}</div>
                ))}
            </div>

            {guesses.map((guess) => (
                <GuessRow key={guess.number} guess={guess} />
            ))}

            {Array.from({ length: empty }, (_, i) => (
                <div key={`empty-${i}`} className="grid grid-cols-5 gap-1.5">
                    {COLUMNS.map((c) => (
                        <div key={c} className="h-14 rounded-lg border-2 border-dashed border-stone-300" />
                    ))}
                </div>
            ))}
        </section>
    );
}

function GuessRow({ guess }: { guess: Guess }) {
    const { street, matches } = guess;

    return (
        <div>
            <p className="mb-1 px-0.5 text-sm font-medium">
                {guess.number}. {street.name}
            </p>
            <div className="grid grid-cols-5 gap-1.5">
                <Tile match={matches.ward} label={street.ward ?? '?'} small />
                <Tile match={matches.postcode_district} label={street.postcode_district ?? '?'} />
                <Tile match={matches.street_type} label={street.street_type ?? '?'} />
                <Tile
                    match={matches.first_letter}
                    label={street.first_letter}
                    hint={guess.first_letter_hint ? `answer is ${guess.first_letter_hint}` : undefined}
                />
                <div className="flex h-14 flex-col items-center justify-center rounded-lg bg-white text-center shadow-sm">
                    {guess.correct ? (
                        <span className="text-xl" aria-label="Correct">🎯</span>
                    ) : (
                        <>
                            <span className="text-lg leading-none" aria-label={`to the ${guess.direction}`}>
                                {ARROWS[guess.direction ?? ''] ?? ''}
                            </span>
                            <span className="mt-1 text-sm font-semibold tabular-nums">
                                {guess.distance_miles.toFixed(1)} mi
                            </span>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

function Tile({ match, label, hint, small = false }: { match: boolean; label: string; hint?: string; small?: boolean }) {
    return (
        <div
            className={`flex h-14 flex-col items-center justify-center rounded-lg px-1 text-center leading-tight font-semibold text-white shadow-sm ${
                match ? 'bg-emerald-600' : 'bg-stone-500'
            } ${small ? 'text-[11px] sm:text-xs' : 'text-sm'}`}
            aria-label={`${label}: ${match ? 'match' : 'no match'}${hint ? `, ${hint}` : ''}`}
        >
            {label}
            {hint && <span className="mt-0.5 text-[10px] font-normal opacity-90">{hint}</span>}
        </div>
    );
}
