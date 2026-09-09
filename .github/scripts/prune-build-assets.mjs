// Bound the deploy branch's `public/build/assets` to TWO builds' worth of files.
//
// Why this exists: a deploy replaces public/build wholesale, deleting the
// previous build's hashed chunks. A browser tab opened *before* the deploy
// still dynamic-imports the old `<Page>-<oldhash>.js`; once that file is gone
// the import 404s and the whole Inertia SPA blanks to a white screen. The
// deploy job carries the previous build's chunks forward (so those tabs keep
// working), and this script then prunes anything older so the branch doesn't
// grow without bound.
//
// SAFETY INVARIANT: files belonging to the *current* build are never deleted.
// The current filenames are passed in explicitly and always kept, so a wrong
// guess here can only shorten the grace window for old tabs — it can never
// remove a chunk the live build needs.
//
// Usage: node prune-build-assets.mjs <currentFilesList> [<previousManifestJson>]
//   currentFilesList     newline-separated basenames of the current build's
//                        public/build/assets files (sacred — always kept)
//   previousManifestJson path to the previous deploy's manifest.json (its
//                        referenced files are kept for one generation of grace)

import { existsSync, readdirSync, readFileSync, rmSync } from 'node:fs';

const [, , currentFilesList, previousManifestJson] = process.argv;
const assetsDir = 'public/build/assets';

if (!existsSync(assetsDir)) {
    console.log(`No ${assetsDir} directory — nothing to prune.`);
    process.exit(0);
}

/** @type {Set<string>} basenames to keep */
const keep = new Set();

// 1. Current build's files — sacred, always kept.
if (currentFilesList && existsSync(currentFilesList)) {
    for (const line of readFileSync(currentFilesList, 'utf8').split('\n')) {
        const name = line.trim();

        if (name) {
            keep.add(name);
        }
    }
}

// 2. The previous deploy's manifest-referenced files — one generation of grace.
// Every chunk/asset is a top-level manifest entry with its own `file`, so
// collecting `file` + `css[]` + `assets[]` across all entries covers the whole
// graph (no transitive walk needed).
if (previousManifestJson && existsSync(previousManifestJson)) {
    try {
        const manifest = JSON.parse(readFileSync(previousManifestJson, 'utf8'));

        for (const entry of Object.values(manifest)) {
            if (!entry || typeof entry !== 'object') {
                continue;
            }

            const refs = [
                entry.file,
                ...(Array.isArray(entry.css) ? entry.css : []),
                ...(Array.isArray(entry.assets) ? entry.assets : []),
            ];

            for (const ref of refs) {
                if (typeof ref === 'string') {
                    keep.add(ref.replace(/^assets\//, ''));
                }
            }
        }
    } catch (error) {
        // A malformed/missing previous manifest just means no extra grace —
        // never a reason to fail the deploy.
        console.warn(`Could not parse previous manifest: ${error.message}`);
    }
}

let kept = 0;
let pruned = 0;

for (const name of readdirSync(assetsDir)) {
    if (keep.has(name)) {
        kept++;
        continue;
    }

    rmSync(`${assetsDir}/${name}`);
    pruned++;
}

console.log(
    `Build-asset retention: kept ${kept}, pruned ${pruned} stale file(s).`,
);
