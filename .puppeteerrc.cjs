// El Chrome que descarga "npm install" queda DENTRO del proyecto (.cache/puppeteer)
// para que el usuario del servidor web (www-data) lo encuentre siempre.
const { join } = require('path');

module.exports = {
    cacheDirectory: join(__dirname, '.cache', 'puppeteer'),
};
