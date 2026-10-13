const sharp = require("sharp");
sharp(process.argv[2], { limitInputPixels: 80000000 })
    .rotate()
    .resize({
        width: 700,
        height: 700,
        fit: "inside",
        withoutEnlargement: true,
    })
    .flatten({ background: "#ffffff" })
    .jpeg({ quality: 82 })
    .toFile(process.argv[3])
    .catch((e) => {
        console.error(e.message);
        process.exit(1);
    });
