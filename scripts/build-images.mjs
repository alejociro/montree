import { mkdir, readdir, rm, stat } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import sharp from 'sharp';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const sourceDir = join(root, 'resources/images/landing');
const outputDir = join(root, 'public/landing');

const sets = [
    { name: 'cocora-wide', widths: [640, 960, 1280, 1600, 1920, 2560, 3200, 3840, 4480, 5120] },
    { name: 'cocora-portrait', widths: [540, 720, 1080, 1440, 1620] },
    { name: 'card-sierra-nevada', widths: [320, 480, 640, 960, 1280] },
    { name: 'card-pago-confirmado', widths: [320, 480, 640, 960, 1280] },
    { name: 'card-cupos', widths: [320, 480, 640, 960, 1280] },
];

const encoders = {
    avif: (pipeline) =>
        pipeline.avif({ quality: 62, effort: 6, chromaSubsampling: '4:4:4' }),
    webp: (pipeline) =>
        pipeline.webp({ quality: 86, effort: 6, smartSubsample: true }),
};

const formatBytes = (bytes) => `${(bytes / 1024).toFixed(1)} KB`;

async function emit(sourcePath, name, width, isNative) {
    for (const [format, encode] of Object.entries(encoders)) {
        const target = join(outputDir, `${name}-${width}.${format}`);

        let pipeline = sharp(sourcePath, { limitInputPixels: false });

        if (!isNative) {
            pipeline = pipeline.resize({
                width,
                kernel: sharp.kernel.lanczos3,
                withoutEnlargement: true,
                fit: 'inside',
            });
        }

        await encode(pipeline).toFile(target);

        const { size } = await stat(target);
        const note = isNative ? ' (native, re-encode only)' : '';
        console.log(`  ${name}-${width}.${format}  ${formatBytes(size)}${note}`);
    }
}

async function resolveSource(name) {
    const entries = await readdir(sourceDir);
    const match = entries.find((entry) => entry.replace(/\.[^.]+$/, '') === name);

    if (!match) {
        throw new Error(`No master found for "${name}" in resources/images/landing`);
    }

    return join(sourceDir, match);
}

await rm(outputDir, { recursive: true, force: true });
await mkdir(outputDir, { recursive: true });

for (const { name, widths } of sets) {
    const sourcePath = await resolveSource(name);
    const { width: masterWidth, height: masterHeight } = await sharp(sourcePath).metadata();

    console.log(`\n${name}  master ${masterWidth}x${masterHeight}`);

    const usable = widths.filter((width) => width < masterWidth);
    usable.push(masterWidth);

    for (const width of usable) {
        await emit(sourcePath, name, width, width === masterWidth);
    }

    const skipped = widths.filter((width) => width > masterWidth);

    if (skipped.length > 0) {
        console.log(`  skipped (master too small): ${skipped.join(', ')}`);
    }
}

console.log('');
