import { useState } from 'react';
import type { Guess } from '../api';
import { knowledge } from '../knowledge';

type Props = {
    guesses: Guess[];
    /** Postcode districts still possible, with how many candidate streets each. */
    districts: Record<string, number>;
    onPickDistrict: (district: string) => void;
};

export default function Knowledge({ guesses, districts, onPickDistrict }: Props) {
    const [showDistricts, setShowDistricts] = useState(false);
    const remaining = Object.entries(districts);

    // One district left means the postcode is effectively known, even without a green tile.
    const facts = knowledge(guesses).map((fact) =>
        fact.label === 'Postcode' && !fact.known && remaining.length === 1
            ? { ...fact, value: `${remaining[0][0]} (the only one left)`, known: true }
            : fact,
    );

    if (!facts.length) return null;

    const postcodeKnown = facts.some((f) => f.label === 'Postcode' && f.known);

    return (
        <section aria-label="What you know" className="rounded-xl bg-white px-4 py-3 shadow-sm">
            <h2 className="text-xs font-semibold tracking-wide text-stone-500 uppercase">What you know</h2>
            <dl className="mt-2 flex flex-wrap gap-2 text-sm">
                {facts.map((fact) => (
                    <div
                        key={fact.label}
                        className={`rounded-lg px-2.5 py-1 ${fact.known ? 'bg-emerald-600 text-white' : 'bg-stone-100 text-stone-700'}`}
                    >
                        <dt className="inline font-semibold">{fact.label}:</dt> <dd className="inline">{fact.value}</dd>
                    </div>
                ))}
            </dl>

            {postcodeKnown && remaining.length === 1 && (
                <button
                    type="button"
                    onClick={() => onPickDistrict(remaining[0][0])}
                    className="mt-3 text-sm font-medium text-emerald-700 hover:underline"
                >
                    List the {remaining[0][0]} streets ({remaining[0][1]})
                </button>
            )}

            {!postcodeKnown && remaining.length > 0 && (
                <div className="mt-3">
                    <button
                        type="button"
                        aria-expanded={showDistricts}
                        onClick={() => setShowDistricts((s) => !s)}
                        className="text-sm font-medium text-emerald-700 hover:underline"
                    >
                        {showDistricts ? 'Hide' : 'Show'} remaining postcodes ({remaining.length})
                    </button>

                    {showDistricts && (
                        <div className="mt-2">
                            <div className="flex flex-wrap gap-1.5">
                                {remaining.map(([district, count]) => (
                                    <button
                                        key={district}
                                        type="button"
                                        onClick={() => onPickDistrict(district)}
                                        className="rounded-lg border border-stone-200 px-2.5 py-1 text-sm hover:border-emerald-600 hover:bg-emerald-50"
                                    >
                                        <span className="font-semibold">{district}</span>{' '}
                                        <span className="text-stone-500">
                                            · {count} {count === 1 ? 'street' : 'streets'}
                                        </span>
                                    </button>
                                ))}
                            </div>
                            <p className="mt-1.5 text-xs text-stone-500">Tap one to list its streets.</p>
                        </div>
                    )}
                </div>
            )}
        </section>
    );
}
