[CmdletBinding()]
param(
    [string]$PythonPath,
    [string]$InnoPath,
    [switch]$PortableOnly,
    [switch]$CheckPrerequisites
)
$ErrorActionPreference = 'Stop'

function Check-Exit($Step) {
    if ($LASTEXITCODE -ne 0) { throw "Fallo: $Step (codigo $LASTEXITCODE)" }
}

function Test-SupportedPython {
    param([string]$Candidate, [string[]]$Prefix = @())
    if ([string]::IsNullOrWhiteSpace($Candidate)) { return $null }
    $Probe = "import json,sys,struct,sysconfig; print(json.dumps({'exe':sys.executable,'major':sys.version_info[0],'minor':sys.version_info[1],'bits':struct.calcsize('P')*8,'implementation':sys.implementation.name,'free_threaded':bool(sysconfig.get_config_var('Py_GIL_DISABLED'))}))"
    try {
        $Output = & $Candidate @Prefix -I -c $Probe 2>$null
        if ($LASTEXITCODE -ne 0) { return $null }
        $Info = ($Output -join "`n") | ConvertFrom-Json -ErrorAction Stop
        if ($Info.major -ne 3 -or $Info.minor -notin @(12,13,14) -or $Info.bits -ne 64 -or $Info.implementation -ne 'cpython' -or $Info.free_threaded) { return $null }
        if (-not (Test-Path -LiteralPath $Info.exe -PathType Leaf)) { return $null }
        return [pscustomobject]@{ Executable = [string]$Info.exe; Version = "$($Info.major).$($Info.minor) x64" }
    } catch { return $null }
}

function Find-SupportedPython {
    param([string]$Override, [string]$ProjectRoot)
    if ($Override) {
        $Found = Test-SupportedPython -Candidate $Override
        if (-not $Found) { throw "-PythonPath no apunta a un CPython 3.12, 3.13 o 3.14 de 64 bits (version estandar) valido: $Override" }
        return $Found
    }
    $Paths = New-Object 'System.Collections.Generic.List[string]'
    $Paths.Add((Join-Path $ProjectRoot '.venv\Scripts\python.exe'))
    foreach ($Name in @('python', 'python.exe', 'python3', 'python3.exe', 'python3.14.exe', 'python3.13.exe', 'python3.12.exe')) {
        $Command = Get-Command $Name -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($Command) { $Paths.Add($Command.Source) }
    }
    foreach ($Root in @('HKCU:\Software\Python\PythonCore', 'HKLM:\Software\Python\PythonCore', 'HKLM:\Software\WOW6432Node\Python\PythonCore')) {
        foreach ($Version in @(Get-ChildItem -LiteralPath $Root -ErrorAction SilentlyContinue)) {
            if ($Version.PSChildName -notmatch '^3\.(12|13|14)($|[-.])') { continue }
            try {
                $Install = Get-Item -LiteralPath (Join-Path $Version.PSPath 'InstallPath') -ErrorAction Stop
                $Exe = $Install.GetValue('ExecutablePath')
                if ($Exe) { $Paths.Add([string]$Exe) }
                $Directory = $Install.GetValue('')
                if ($Directory) { $Paths.Add((Join-Path $Directory 'python.exe')) }
            } catch { }
        }
    }
    foreach ($Version in @('314','313','312')) {
        if ($env:LOCALAPPDATA) { $Paths.Add((Join-Path $env:LOCALAPPDATA "Programs\Python\Python$Version\python.exe")) }
        if ($env:ProgramFiles) { $Paths.Add((Join-Path $env:ProgramFiles "Python$Version\python.exe")) }
        if ($env:SystemDrive) { $Paths.Add((Join-Path $env:SystemDrive "Python$Version\python.exe")) }
    }
    foreach ($Path in @($Paths | Select-Object -Unique)) {
        if (Test-Path -LiteralPath $Path -PathType Leaf) {
            $Found = Test-SupportedPython -Candidate $Path
            if ($Found) { return $Found }
        }
    }
    # El lanzador es opcional; no se ejecuta si Windows no lo encuentra.
    $Launcher = Get-Command 'py.exe' -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($Launcher) {
        foreach ($Version in @('-3.14','-3.13','-3.12')) {
            $Found = Test-SupportedPython -Candidate $Launcher.Source -Prefix @($Version)
            if ($Found) { return $Found }
        }
    }
    return $null
}

function Find-InnoCompiler {
    param([string]$Override)
    if ($Override) {
        if (-not (Test-Path -LiteralPath $Override -PathType Leaf)) { throw "No existe el compilador indicado en -InnoPath: $Override" }
        return $Override
    }
    $Command = Get-Command 'ISCC.exe' -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($Command) { return $Command.Source }
    $Paths = @()
    foreach ($InnoVersion in @('7','6')) {
        if (${env:ProgramFiles(x86)}) { $Paths += Join-Path ${env:ProgramFiles(x86)} "Inno Setup $InnoVersion\ISCC.exe" }
        if ($env:ProgramFiles) { $Paths += Join-Path $env:ProgramFiles "Inno Setup $InnoVersion\ISCC.exe" }
        if ($env:LOCALAPPDATA) { $Paths += Join-Path $env:LOCALAPPDATA "Programs\Inno Setup $InnoVersion\ISCC.exe" }
    }
    foreach ($Path in $Paths) { if (Test-Path -LiteralPath $Path -PathType Leaf) { return $Path } }
    return $null
}

if ([Environment]::OSVersion.Platform -ne [PlatformID]::Win32NT) {
    throw 'Este script genera aplicaciones de Windows y debe ejecutarse en Windows.'
}
Set-Location -LiteralPath $PSScriptRoot
$Detected = Find-SupportedPython -Override $PythonPath -ProjectRoot $PSScriptRoot
if (-not $Detected) {
    throw @'
No se encontro CPython 3.12, 3.13 o 3.14 de 64 bits (version estandar). El lanzador py no es obligatorio.
Instale Python con:
  winget install -e --id Python.Python.3.14 --scope user --architecture x64
Cierre y abra PowerShell; despues vuelva a ejecutar este script.
Si ya tiene Python compatible, puede indicar su ruta:
  .\Compilar-Windows.ps1 -PythonPath "C:\ruta\Python314\python.exe"
'@
}
Write-Host "Python encontrado: $($Detected.Executable) ($($Detected.Version))"
$Compiler = $null
if (-not $PortableOnly) {
    $Compiler = Find-InnoCompiler -Override $InnoPath
    if (-not $Compiler) {
        throw @'
Se encontro Python, pero falta Inno Setup 6 o 7 para crear el instalador.
Instalelo desde https://jrsoftware.org/isdl.php y vuelva a ejecutar este script.
Para crear solamente el EXE portable, use -PortableOnly.
Si ya esta instalado en otra carpeta, indique -InnoPath "C:\ruta\ISCC.exe".
'@
    }
    Write-Host "Inno Setup encontrado: $Compiler"
}
if ($CheckPrerequisites) {
    Write-Host 'Requisitos comprobados. No se instalaron dependencias ni se compilo el programa.'
    return
}
$Venv = Join-Path $PSScriptRoot '.venv\Scripts\python.exe'
if (Test-Path -LiteralPath $Venv) {
    if (-not (Test-SupportedPython -Candidate $Venv)) {
        throw 'La carpeta .venv existente no usa Python compatible x64 o esta incompleta. Renombre solo esa carpeta de compilacion y vuelva a intentar. No se borraron datos.'
    }
} else {
    & $Detected.Executable -m venv .venv
    Check-Exit 'Crear entorno Python'
}
$Python = $Venv
& $Python -m pip install --upgrade pip
Check-Exit 'Actualizar pip'
& $Python -m pip install -r requirements.txt 'pyinstaller>=6.22,<7'
Check-Exit 'Instalar dependencias'
& $Python -m unittest discover -s tests -v
Check-Exit 'Pruebas Python'
& $Python -m PyInstaller --noconfirm --clean --windowed --onedir --name MiCVDesktop --add-data 'assets;assets' --collect-data openpyxl main.py
Check-Exit 'Compilar ejecutable'
$NoticeDir = Join-Path $PSScriptRoot 'dist\MiCVDesktop\licenses'
New-Item -ItemType Directory -Force -Path $NoticeDir | Out-Null
Copy-Item 'THIRD-PARTY.txt' $NoticeDir
Get-ChildItem '.venv\Lib\site-packages' -Directory -Filter '*.dist-info' | ForEach-Object {
    $Target = Join-Path $NoticeDir $_.Name
    if (Test-Path (Join-Path $_.FullName 'licenses')) { Copy-Item (Join-Path $_.FullName 'licenses') $Target -Recurse -Force }
    Get-ChildItem $_.FullName -File | Where-Object { $_.Name -match '^(LICENSE|COPYING|NOTICE)' } | ForEach-Object {
        New-Item -ItemType Directory -Force -Path $Target | Out-Null
        Copy-Item $_.FullName $Target
    }
}
$SmokeHome = Join-Path $env:TEMP ('micv-smoke-' + [guid]::NewGuid().ToString())
$PreviousHome = $env:MICV_DESKTOP_HOME
try {
    $env:MICV_DESKTOP_HOME = $SmokeHome
    $Run = Start-Process -FilePath '.\dist\MiCVDesktop\MiCVDesktop.exe' -ArgumentList '--smoke-test' -Wait -PassThru
    if ($Run.ExitCode -ne 0) { throw 'El ejecutable no supero la comprobacion de arranque.' }
} finally { $env:MICV_DESKTOP_HOME = $PreviousHome }
if ($PortableOnly) {
    Write-Host 'EXE portable generado: desktop\dist\MiCVDesktop\MiCVDesktop.exe'
    Write-Host 'Distribuya toda la carpeta MiCVDesktop, no solo el archivo EXE.'
    return
}
& $Compiler 'installer\setup.iss'
Check-Exit 'Generar instalador'
Write-Host 'Instalador generado: desktop\dist\installer\MiCVDesktop-Setup.exe'
