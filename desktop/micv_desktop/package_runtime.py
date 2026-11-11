"""Assemble a Windows runtime from project sources and supplied engine binaries."""
import argparse
import os
from pathlib import Path
import shutil
import tempfile

PROJECT_DIRECTORIES = ('app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'vendor', 'node_modules')


def copy_filtered(source, destination):
    destination.mkdir(parents=True, exist_ok=True)
    for item in source.iterdir():
        if item.is_symlink():
            raise ValueError('El paquete contiene un enlace simbólico: ' + str(item))
        if item.name.startswith('.env') or item.name in ('.npmrc', '.git', '__pycache__', '.cache') or item.suffix in ('.sqlite', '.key', '.log', '.pyc'):
            continue
        target = destination / item.name
        if item.is_dir():
            copy_filtered(item, target)
        elif item.is_file():
            shutil.copy2(item, target)


def build_runtime(project, engines, output):
    project, engines, output = Path(project).resolve(), Path(engines).resolve(), Path(output).absolute()
    if output.exists():
        raise ValueError('Use una carpeta de salida nueva para preparar el motor.')
    for source in (project, engines):
        if output == source or source in output.parents:
            raise ValueError('La salida debe estar fuera de las carpetas de entrada.')
    for relative in ('artisan', 'vendor/autoload.php', 'public/index.php', 'node_modules/puppeteer/package.json', 'node_modules/sharp/package.json'):
        if not (project / relative).is_file():
            raise ValueError('Falta una dependencia del proyecto: ' + relative)
    for relative in ('php/php.exe', 'node/node.exe', 'chrome/chrome.exe'):
        binary = engines / relative
        if not binary.is_file():
            raise ValueError('Falta un motor Windows válido: ' + relative)
        with binary.open('rb') as handle:
            signature = handle.read(2)
        if signature != b'MZ':
            raise ValueError('Falta un motor Windows válido: ' + relative)
    output.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='runtime-', dir=output.parent) as directory:
        staging = Path(directory) / 'bundle'
        app = staging / 'laravel'
        app.mkdir(parents=True)
        for name in PROJECT_DIRECTORIES:
            copy_filtered(project / name, app / name)
        cache = app / 'bootstrap/cache'
        if cache.exists():
            shutil.rmtree(cache)
        cache.mkdir()
        shutil.copy2(project / 'artisan', app / 'artisan')
        shutil.copy2(project / 'composer.json', app / 'composer.json')
        for name in ('php', 'node', 'chrome'):
            copy_filtered(engines / name, staging / name)
        models = project / 'storage/app/modelos'
        if models.is_dir():
            copy_filtered(models, staging / 'models')
        redistributable = engines / 'vc_redist.x64.exe'
        if redistributable.is_file():
            shutil.copy2(redistributable, staging / redistributable.name)
        shutil.copy2(project / 'desktop/runtime/router.php', staging / 'router.php')
        os.replace(staging, output)
    return output


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--project', required=True)
    parser.add_argument('--engines', required=True)
    parser.add_argument('--output', required=True)
    args = parser.parse_args()
    print(build_runtime(args.project, args.engines, args.output))


if __name__ == '__main__':
    main()
