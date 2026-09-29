import { useEffect, useMemo, useRef, useState } from 'react';
import { api, ApiError, type GameState, type StreetOption } from './api';
import GuessGrid from './components/GuessGrid';
import Knowledge from './components/Knowledge';
import PuzzleImage from './components/PuzzleImage';
import Remaining from './components/Remaining';
import Result from './components/Result';
import StreetSearch from './components/StreetSearch';

export default function App() {
    const [game, setGame] = useState<GameState | null>(null);
    const [streets, setStreets] = useState<StreetOption[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [query, setQuery] = useState('');
    const searchInput = useRef<HTMLInputElement>(null);

    useEffect(() => {
        Promise.all([api.puzzle(), api.streets()])
            .then(([puzzle, list]) => {
                setGame(puzzle);
                setStreets(list);
            })
            .catch((e: Error) => setError(e.message));
    }, []);

    const guessed = useMemo(() => new Set(game?.guesses.map((g) => g.street.id)), [game]);
    // Nothing is ruled out before the first guess, so there's nothing to dim.
    const possible = useMemo(() => (game?.guesses.length ? new Set(game.candidates) : null), [game]);

    async function guess(street: StreetOption) {
        if (!game) return;

        setSubmitting(true);
        setError(null);

        try {
            setGame(await api.guess(street.id, game.puzzle.number));
        } catch (e) {
            setError(e instanceof ApiError ? e.message : 'Something went wrong. Please try again.');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="mx-auto flex max-w-2xl flex-col gap-5 px-4 py-6 sm:py-10">
            <header className="flex items-baseline justify-between gap-4">
                <h1 className="text-3xl font-bold tracking-tight">Salfordle</h1>
                {game && (
                    <p className="text-sm text-stone-500">
                        #{game.puzzle.number} · {formatDate(game.puzzle.date)}
                    </p>
                )}
            </header>

            <details className="rounded-lg bg-white px-4 py-3 text-sm text-stone-600 shadow-sm">
                <summary className="cursor-pointer font-medium text-stone-800">How to play</summary>
                <div className="mt-2 space-y-2">
                    <p>
                        Name the Salford street in the picture in {game?.max_guesses ?? 6} guesses. Search by street
                        name, or by postcode if you can spot one.
                    </p>
                    <p>
                        Each guess shows how your street compares with the answer:{' '}
                        <strong className="text-emerald-700">green</strong> for the same ward, postcode district or
                        street type, <strong className="text-stone-500">grey</strong> if not. For the first letter
                        you're told whether the answer's comes before or after yours. The arrow points from your
                        street towards the answer.
                    </p>
                    <p>
                        Every street those clues rule out is dimmed in the search, and the count shows how many are
                        left. Distance is just a hint: it never rules anything out.
                    </p>
                </div>
            </details>

            {!game && !error && <p className="py-20 text-center text-stone-500">Loading today's street…</p>}

            {game && (
                <>
                    <PuzzleImage url={game.puzzle.image_url} />

                    <Remaining
                        remaining={game.remaining}
                        total={game.total_streets}
                        used={game.guesses.length}
                        max={game.max_guesses}
                        playing={game.status === 'playing'}
                    />

                    {game.status === 'playing' ? (
                        <>
                            <StreetSearch
                                streets={streets}
                                exclude={guessed}
                                possible={possible}
                                disabled={submitting}
                                onPick={guess}
                                query={query}
                                onQueryChange={setQuery}
                                inputRef={searchInput}
                            />
                            <Knowledge
                                guesses={game.guesses}
                                districts={game.remaining_postcode_districts}
                                onPickDistrict={(district) => {
                                    setQuery(district);
                                    searchInput.current?.focus();
                                }}
                            />
                        </>
                    ) : (
                        <Result game={game} />
                    )}
                </>
            )}

            {error && (
                <p role="alert" className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                    {error}
                </p>
            )}

            {game && <GuessGrid guesses={game.guesses} max={game.max_guesses} />}

            <footer className="mt-6 border-t border-stone-200 pt-4 text-xs leading-relaxed text-stone-500">
                Street data ©{' '}
                <a className="underline" href="https://www.openstreetmap.org/copyright">
                    OpenStreetMap contributors
                </a>{' '}
                (ODbL). Ward boundaries and postcodes: Office for National Statistics, via postcodes.io. Contains OS
                data © Crown copyright and database right, licensed under the Open Government Licence. Imagery ©
                Google.
            </footer>
        </div>
    );
}

function formatDate(date: string): string {
    return new Date(`${date}T12:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
}
