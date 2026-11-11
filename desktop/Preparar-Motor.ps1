[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$Python, [Parameter(Mandatory=$true)][string]$Destination)
$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
function Read-ChecksumFile([string]$Url, [string]$File) {
    Invoke-WebRequest -UseBasicParsing -Uri $Url -OutFile $File
    Get-Content -LiteralPath $File -Raw -Encoding ASCII
}
function Download-Checked($Url, $File, [string]$Hash, [ValidateSet('SHA256','SHA384','MD5')][string]$Algorithm = 'SHA256') {
    $Hash = $Hash.Trim()
    $HashLength = @{SHA256 = 64; SHA384 = 96; MD5 = 32}[$Algorithm]
    if ($Hash.Length -ne $HashLength -or $Hash -notmatch '\A[0-9a-f]+\z') {
        throw "La suma $Algorithm publicada no tiene un formato valido: $Url"
    }
    Invoke-WebRequest -UseBasicParsing -Uri $Url -OutFile $File
    if ((Get-FileHash -LiteralPath $File -Algorithm $Algorithm).Hash -ne $Hash) {
        Remove-Item -LiteralPath $File
        throw "La descarga no coincide con la firma publicada: $Url"
    }
}
function Run-Checked($Program, $Arguments) {
    $PreviousExtensionPath = $env:MICV_PHP_EXT
    try {
        if ($Program -eq $Php) { $env:MICV_PHP_EXT = Join-Path (Split-Path $Php -Parent) 'ext' }
        & $Program @Arguments
        if ($LASTEXITCODE -ne 0) { throw "No se pudo ejecutar $Program (codigo $LASTEXITCODE)." }
    } finally { $env:MICV_PHP_EXT = $PreviousExtensionPath }
}
if (Test-Path -LiteralPath $Destination) { throw 'La carpeta de preparación ya existe.' }
New-Item -ItemType Directory -Path $Destination | Out-Null
$Project = Join-Path $Destination 'project'
$Engines = Join-Path $Destination 'engines'
$Source = Split-Path $PSScriptRoot -Parent
New-Item -ItemType Directory -Path $Project,$Engines | Out-Null
foreach ($Name in @('app','bootstrap','config','database','lang','public','resources','routes')) {
    Copy-Item -LiteralPath (Join-Path $Source $Name) -Destination $Project -Recurse
}
foreach ($Name in @('artisan','composer.json','composer.lock','package.json','package-lock.json')) {
    Copy-Item -LiteralPath (Join-Path $Source $Name) -Destination $Project
}
$RouterDirectory = Join-Path $Project 'desktop\runtime'
New-Item -ItemType Directory -Path $RouterDirectory -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'runtime\router.php') -Destination $RouterDirectory
$BootstrapCache = Join-Path $Project 'bootstrap\cache'
if (Test-Path $BootstrapCache) { Remove-Item -LiteralPath $BootstrapCache -Recurse -Force }
New-Item -ItemType Directory -Path $BootstrapCache | Out-Null

# Artisan needs writable Laravel directories in the temporary build project.
$Storage = Join-Path $Project 'storage'
foreach ($Relative in @('app\private','app\public','framework\cache\data','framework\sessions','framework\views','logs')) {
    New-Item -ItemType Directory -Path (Join-Path $Storage $Relative) -Force | Out-Null
}

Write-Host 'Preparando Microsoft Visual C++ x64...'
$Redistributable = Join-Path $Engines 'vc_redist.x64.exe'
Invoke-WebRequest -UseBasicParsing -Uri 'https://aka.ms/vs/17/release/vc_redist.x64.exe' -OutFile $Redistributable
$Authenticode = Get-AuthenticodeSignature -LiteralPath $Redistributable
if ($Authenticode.Status -ne 'Valid' -or $Authenticode.SignerCertificate.Subject -notmatch 'O=Microsoft Corporation') {
    throw 'La firma del componente de Microsoft no es válida.'
}
$VcRuntime = Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64' -ErrorAction SilentlyContinue
if ($VcRuntime.Installed -ne 1) {
    $InstallVc = Start-Process -FilePath $Redistributable -ArgumentList '/install /quiet /norestart' -Verb RunAs -Wait -PassThru
    if ($InstallVc.ExitCode -notin @(0,1638,3010)) { throw 'No se pudo instalar Microsoft Visual C++ x64.' }
}

Write-Host 'Descargando PHP x64 y comprobando SHA-256...'
$Releases = Invoke-RestMethod 'https://downloads.php.net/~windows/releases/releases.json'
$Release = $Releases.'8.4'.'nts-vs17-x64'.zip
if (-not $Release.path -or -not $Release.sha256) { throw 'No se encontró PHP 8.4 NTS x64 en el catálogo oficial.' }
$Archive = Join-Path $Destination 'php.zip'
Download-Checked ('https://downloads.php.net/~windows/releases/' + $Release.path) $Archive $Release.sha256
$PhpDirectory = Join-Path $Engines 'php'
Expand-Archive -LiteralPath $Archive -DestinationPath $PhpDirectory
$Php = Join-Path $PhpDirectory 'php.exe'
$Ini = @('extension_dir="${MICV_PHP_EXT}"','date.timezone=America/Managua','memory_limit=512M')
$Extensions = @('curl','fileinfo','intl','mbstring','openssl','pdo_sqlite','sqlite3','sodium','zip')
foreach ($Extension in $Extensions) {
    $Ini += 'extension=' + $Extension
}
Set-Content -LiteralPath (Join-Path $PhpDirectory 'php.ini') -Value $Ini -Encoding ASCII
$PreviousExtensionPath = $env:MICV_PHP_EXT
try {
    $env:MICV_PHP_EXT = Join-Path $PhpDirectory 'ext'
    $LoadedExtensions = & $Php -m
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron comprobar las extensiones de PHP.' }
    foreach ($Extension in $Extensions) {
        if ($LoadedExtensions -notcontains $Extension) { throw "Falta la extensión de PHP $Extension." }
    }
} finally { $env:MICV_PHP_EXT = $PreviousExtensionPath }

Write-Host 'Descargando Node LTS x64 y comprobando SHA-256...'
$NodeReleases = Invoke-RestMethod 'https://nodejs.org/dist/index.json'
$NodeRelease = $NodeReleases | Where-Object { $_.version -match '^v24\.' -and $_.lts -and $_.files -contains 'win-x64-zip' } | Select-Object -First 1
if (-not $NodeRelease) { throw 'No se encontró Node 24 LTS x64.' }
$NodeName = 'node-' + $NodeRelease.version + '-win-x64.zip'
$NodeUrl = 'https://nodejs.org/dist/' + $NodeRelease.version + '/'
$Checksums = Read-ChecksumFile ($NodeUrl + 'SHASUMS256.txt') (Join-Path $Destination 'SHASUMS256.txt')
$Line = ($Checksums -split "\r?\n" | Where-Object { $_.Trim().EndsWith('  ' + $NodeName) } | Select-Object -First 1)
if (-not $Line) { throw 'Falta el SHA-256 de Node.' }
$NodeArchive = Join-Path $Destination 'node.zip'
Download-Checked ($NodeUrl + $NodeName) $NodeArchive (($Line -split '\s+')[0])
Expand-Archive -LiteralPath $NodeArchive -DestinationPath $Destination
Move-Item -LiteralPath (Join-Path $Destination ('node-' + $NodeRelease.version + '-win-x64')) -Destination (Join-Path $Engines 'node')
$Node = Join-Path $Engines 'node\node.exe'
$NpmCli = Join-Path $Engines 'node\node_modules\npm\bin\npm-cli.js'

Write-Host 'Preparando Composer con verificación SHA-384...'
$Setup = Join-Path $Destination 'composer-setup.php'
$Signature = Read-ChecksumFile 'https://composer.github.io/installer.sig' (Join-Path $Destination 'composer-installer.sig')
Download-Checked 'https://getcomposer.org/installer' $Setup $Signature 'SHA384'
Run-Checked $Php @($Setup,('--install-dir=' + $Destination),'--filename=composer.phar','--quiet')
Run-Checked $Php @((Join-Path $Destination 'composer.phar'),'install',('--working-dir=' + $Project),'--no-dev','--no-interaction','--no-scripts','--no-plugins','--prefer-dist','--optimize-autoloader')

Write-Host 'Descargando el modelo U2-Net para fotos...'
$Models = Join-Path $Storage 'app\modelos'
New-Item -ItemType Directory -Path $Models -Force | Out-Null
$Model = Join-Path $Models 'u2net_human_seg.onnx'
$ModelUrl = 'https://github.com/danielgatis/rembg/releases/download/v0.0.0/u2net_human_seg.onnx'
# Use Windows PowerShell's HTTPS trust and the checksum published by rembg.
Download-Checked $ModelUrl $Model 'c09ddc2e0104f800e3e1bb4652583d1f' 'MD5'

$PreviousPath = $env:PATH
$PreviousBrowserCache = $env:PUPPETEER_CACHE_DIR
try {
    $env:PATH = (Join-Path $Engines 'node') + ';' + $PreviousPath
    $env:PUPPETEER_CACHE_DIR = Join-Path $Destination 'browser-cache'
    Push-Location -LiteralPath $Project
    try {
        Run-Checked $Node @($NpmCli,'ci','--omit=dev','--no-audit','--no-fund')
        Run-Checked $Node @('node_modules\puppeteer\lib\cjs\puppeteer\node\cli.js','browsers','install','chrome')
        $Chrome = & $Node -e "console.log(require('puppeteer').executablePath())"
        if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $Chrome)) { throw 'No se pudo preparar Chromium.' }
        Copy-Item -LiteralPath (Split-Path $Chrome -Parent) -Destination (Join-Path $Engines 'chrome') -Recurse
        Run-Checked $Node @('-e',"require('sharp'); require('puppeteer'); require('exceljs'); require('docx'); require('onnxruntime-web');")

        Write-Host 'Comprobando el quitafondos con el modelo local...'
        $Image = Join-Path $Destination 'fondo-prueba.png'
        $Result = Join-Path $Destination 'fondo-prueba-salida.png'
        Run-Checked $Node @('-e',"require('sharp')({create:{width:48,height:64,channels:3,background:'white'}}).png().toFile(process.argv[1]).catch(error=>{console.error(error);process.exitCode=1;});",$Image)
        Run-Checked $Node @('resources\fotos\quitar-fondo.cjs',$Image,$Result,$Model,'transparente')
        Run-Checked $Node @('-e',"require('sharp')(process.argv[1]).metadata().then(image=>{if(image.format!=='png'||image.width!==48||image.height!==64||!image.hasAlpha){throw Error('Salida del quitafondos invalida');}}).catch(error=>{console.error(error);process.exitCode=1;});",$Result)
    } finally { Pop-Location }
} finally {
    $env:PATH = $PreviousPath
    $env:PUPPETEER_CACHE_DIR = $PreviousBrowserCache
}
& $Python -m micv_desktop.package_runtime --project $Project --engines $Engines --output (Join-Path $Destination 'bundle')
if ($LASTEXITCODE -ne 0) { throw 'No se pudo ensamblar el motor local.' }
