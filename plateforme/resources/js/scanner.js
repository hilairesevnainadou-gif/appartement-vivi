/*
 * Lecture d'un QR Code avec la caméra du téléphone.
 * BarcodeDetector (intégré à Chrome Android) quand il existe, sinon jsQR sur les images de la vidéo.
 * La caméra exige une connexion sécurisée (https), ou localhost pendant le développement.
 */
import jsQR from 'jsqr';

export async function demarrerLecture(video, quandLu) {
    const flux = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
    video.srcObject = flux;
    video.setAttribute('playsinline', '');
    await video.play();

    const detecteur = 'BarcodeDetector' in window
        ? new window.BarcodeDetector({ formats: ['qr_code'] })
        : null;
    const toile = document.createElement('canvas');
    const ctx = toile.getContext('2d', { willReadFrequently: true });
    let actif = true;

    async function lire() {
        if (!actif) return;
        if (video.readyState >= 2) {
            let texte = null;
            if (detecteur) {
                const codes = await detecteur.detect(video).catch(() => []);
                texte = codes[0]?.rawValue ?? null;
            } else {
                toile.width = video.videoWidth;
                toile.height = video.videoHeight;
                ctx.drawImage(video, 0, 0, toile.width, toile.height);
                const image = ctx.getImageData(0, 0, toile.width, toile.height);
                texte = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' })?.data ?? null;
            }
            if (texte) {
                arreter();
                quandLu(texte);
                return;
            }
        }
        requestAnimationFrame(lire);
    }

    function arreter() {
        actif = false;
        flux.getTracks().forEach((piste) => piste.stop());
        video.srcObject = null;
    }

    requestAnimationFrame(lire);

    return arreter;
}

// Utilisée par la page « Scanner un QR Code »
window.viviScanner = { demarrerLecture };
