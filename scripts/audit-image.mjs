import { basename } from 'node:path';

import sharp from 'sharp';

const [, , ...files] = process.argv;

if (files.length === 0) {
    console.error('Uso: node scripts/audit-image.mjs <imagen> [imagen...]');
    process.exit(1);
}

const probes = [0.25, 0.35, 0.5, 0.65, 0.8, 0.9];

const rootMeanSquareError = (a, b) => {
    let sum = 0;

    for (let i = 0; i < a.length; i++) {
        const delta = a[i] - b[i];
        sum += delta * delta;
    }

    return Math.sqrt(sum / a.length);
};

async function audit(file) {
    const image = sharp(file, { limitInputPixels: false });
    const { width, height, format } = await image.metadata();
    const reference = await sharp(file).greyscale().raw().toBuffer();

    console.log(`\n${basename(file)}  ${width}x${height}  ${format}`);
    console.log('  fracción  ancho   RMSE vs original');

    const readings = [];

    for (const fraction of probes) {
        const probeWidth = Math.round(width * fraction);

        const shrunk = await sharp(file, { limitInputPixels: false })
            .resize({ width: probeWidth, kernel: sharp.kernel.lanczos3 })
            .png()
            .toBuffer();

        const restored = await sharp(shrunk)
            .resize({ width, height, kernel: sharp.kernel.lanczos3, fit: 'fill' })
            .greyscale()
            .raw()
            .toBuffer();

        const error = rootMeanSquareError(reference, restored);
        readings.push({ fraction, probeWidth, error });

        console.log(
            `  ${fraction.toFixed(2)}      ${String(probeWidth).padStart(5)}   ${error.toFixed(2)}`,
        );
    }

    const worst = readings[0].error;
    const best = readings.at(-1).error;
    const spread = worst - best;

    const knee = readings.find((reading) => reading.error - best <= spread * 0.15);
    const effective = knee ? knee.probeWidth : width;
    const ratio = effective / width;

    console.log(`\n  Resolución efectiva estimada: ~${effective}px de ${width}px (${Math.round(ratio * 100)}%)`);

    if (ratio < 0.75) {
        console.log('  ❌ RECHAZAR — la imagen fue reescalada hacia arriba; no tiene el detalle que aparenta.');
    } else if (ratio < 0.9) {
        console.log('  ⚠️  DUDOSA — poco detalle real para su tamaño (¿sobrecomprimida o suavizada?).');
    } else {
        console.log('  ✅ ACEPTAR — el detalle es consistente con sus dimensiones.');
    }
}

for (const file of files) {
    await audit(file);
}

console.log('');
