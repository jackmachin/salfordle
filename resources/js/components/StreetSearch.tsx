import { useEffect, useId, useMemo, useState } from 'react';
import type { StreetOption } from '../api';
import { searchStreets } from '../search';

type Props = {
    streets: StreetOption[];
    exclude: Set<number>;
    /** Ids still consistent with the clues; null before any guess. */
    possible: Set<number> | null;
    disabled: boolean;
    onPick: (street: StreetOption) => void;
    /** Controlled by the parent, so other controls (e.g. a postcode district chip) can fill it in. */
    query: string;
    onQueryChange: (query: string) => void;
    inputRef?: React.Ref<HTMLInputElement>;
};

export default function StreetSearch({ streets, exclude, possible, disabled, onPick, query, onQueryChange: setQuery, inputRef }: Props) {
    const [active, setActive] = useState(0);

    // The query can also be set from outside (a district chip), so reset the highlight whenever it changes.
    useEffect(() => setActive(0), [query]);
    const listId = useId();

    const { suggestions, total, possibleTotal, mode } = useMemo(() => searchStreets(streets, query, exclude, possible), [query, streets, exclude, possible]);

    function pick(street: StreetOption) {
        onPick(street);
        setQuery('');
        setActive(0);
    }

    function onKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const step = e.key === 'ArrowDown' ? 1 : -1;
            setActive((i) => (suggestions.length ? (i + step + suggestions.length) % suggestions.length : 0));
        } else if (e.key === 'Enter' && suggestions[active]) {
            e.preventDefault();
            pick(suggestions[active].street);
        } else if (e.key === 'Escape') {
            setQuery('');
        }
    }

    const open = suggestions.length > 0;

    return (
        <div className="relative">
            <input
                ref={inputRef}
                type="text"
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                aria-activedescendant={open ? `${listId}-${active}` : undefined}
                aria-label="Guess a street by name or postcode"
                placeholder={disabled ? 'Checking…' : 'Street name or postcode…'}
                autoComplete="off"
                spellCheck={false}
                disabled={disabled}
                value={query}
                onChange={(e) => {
                    setQuery(e.target.value);
                    setActive(0);
                }}
                onKeyDown={onKeyDown}
                className="w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-lg shadow-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 disabled:opacity-60"
            />

            {open && (
                <div className="absolute z-10 mt-1 w-full overflow-hidden rounded-xl border border-stone-200 bg-white shadow-lg">
                    {(mode === 'postcode' || possible || total > suggestions.length) && (
                        <p className="border-b border-stone-100 px-4 py-2 text-xs text-stone-500">
                            {total > suggestions.length
                                ? `Showing ${suggestions.length} of ${total} streets. Keep typing${mode === 'postcode' ? ' the postcode' : ''} to narrow it down.`
                                : `${total} ${total === 1 ? 'street' : 'streets'} ${mode === 'postcode' ? 'with a matching postcode' : 'match'}`}
                            {possible && ` (${possibleTotal} still possible)`}
                        </p>
                    )}
                    <ul id={listId} role="listbox" className="max-h-80 overflow-auto py-1">
                        {suggestions.map(({ street, postcode, ruledOut }, i) => (
                            <li
                                key={street.id}
                                id={`${listId}-${i}`}
                                role="option"
                                aria-selected={i === active}
                                onMouseDown={(e) => {
                                    e.preventDefault(); // keep focus in the input
                                    pick(street);
                                }}
                                onMouseEnter={() => setActive(i)}
                                className={`flex cursor-pointer items-baseline justify-between gap-3 px-4 py-2 ${
                                    i === active ? 'bg-emerald-50 text-emerald-900' : ''
                                } ${ruledOut ? 'text-stone-400' : ''}`}
                            >
                                <span>
                                    {street.name}
                                    {ruledOut && <span className="ml-2 text-xs">ruled out</span>}
                                </span>
                                {postcode && <span className="shrink-0 text-sm text-stone-500 tabular-nums">{postcode}</span>}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {query.trim() && !open && (
                <p className="mt-2 px-1 text-sm text-stone-500">
                    {mode === 'postcode' ? 'No Salford street has a postcode starting like that.' : 'No Salford street matches that.'}
                </p>
            )}
        </div>
    );
}
