import { useState } from 'react';
import type { GameState } from '../api';
import { shareText } from '../share';

export default function Result({ game }: { game: GameState }) {
    const { answer, status, guesses, max_guesses } = game;

    if (!answer) return null;

    const won = status === 'won';
    const details = [answer.sub_area, answer.ward, answer.postcode_district].filter(Boolean).join(' · ');

    return (
        <div className={`rounded-xl px-5 py-4 shadow-sm ${won ? 'bg-emerald-600 text-white' : 'bg-white'}`}>
            <p className="text-sm font-medium opacity-80">
                {won ? `Solved in ${guesses.length}/${max_guesses}` : 'Out of guesses. It was:'}
            </p>
            <p className="mt-1 text-2xl font-bold">{answer.name}</p>
            {details && <p className="mt-1 text-sm opacity-80">{details}</p>}

            <pre className="mt-4 rounded-lg bg-white px-3 py-2 font-sans text-sm leading-relaxed whitespace-pre-wrap text-stone-800 ring-1 ring-black/5">
                {shareText(game)}
            </pre>

            <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                <ShareButton game={game} inverted={won} />
                <p className="text-sm opacity-80">A new street appears at midnight.</p>
            </div>
        </div>
    );
}

/**
 * The Clipboard API only exists on secure pages (HTTPS or localhost), so plain-HTTP
 * dev hosts like http://salfordle.test fall back to the old select-and-copy command.
 */
async function copy(text: string): Promise<boolean> {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            // e.g. permission denied: try the fallback
        }
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    try {
        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        textarea.remove();
    }
}

function ShareButton({ game, inverted }: { game: GameState; inverted: boolean }) {
    const [feedback, setFeedback] = useState<string | null>(null);

    async function share() {
        const text = shareText(game, window.location.origin);

        // The native share sheet on phones; the clipboard everywhere else.
        if (navigator.share && matchMedia('(pointer: coarse)').matches) {
            try {
                await navigator.share({ text });
                return;
            } catch (e) {
                if (e instanceof DOMException && e.name === 'AbortError') return; // closed the sheet
            }
        }

        flash((await copy(text)) ? 'Copied to clipboard' : "Couldn't copy. Select the text above instead.");
    }

    function flash(message: string) {
        setFeedback(message);
        setTimeout(() => setFeedback(null), 2500);
    }

    return (
        <div className="flex items-center gap-3">
            <button
                type="button"
                onClick={share}
                className={`rounded-lg px-4 py-2 font-semibold shadow-sm transition active:scale-95 ${
                    inverted ? 'bg-white text-emerald-700 hover:bg-emerald-50' : 'bg-emerald-600 text-white hover:bg-emerald-700'
                }`}
            >
                Share result
            </button>
            <span role="status" className="text-sm">
                {feedback}
            </span>
        </div>
    );
}
