param([Parameter(Mandatory=$true)][string]$PythonExecutable)
$ErrorActionPreference = 'Stop'
$ScriptPath = Join-Path (Split-Path $PSScriptRoot -Parent) 'Compilar-Windows.ps1'
$Tokens = $null; $ParseErrors = $null
$Ast = [System.Management.Automation.Language.Parser]::ParseFile($ScriptPath,[ref]$Tokens,[ref]$ParseErrors)
if ($ParseErrors.Count) { throw ($ParseErrors | Out-String) }
foreach ($Definition in $Ast.FindAll({ param($Node) $Node -is [System.Management.Automation.Language.FunctionDefinitionAst] },$true)) {
    Invoke-Expression $Definition.Extent.Text
}
$Count = 0
function Assert-That($Condition,$Message) {
    if (-not $Condition) { throw "FAILED: $Message" }
    $script:Count++
}
$Found = Test-SupportedPython -Candidate $PythonExecutable
Assert-That ($null -ne $Found) 'real compatible CPython x64 executable detected'
$ResolvedPython = $Found.Executable
$TempFolder = Join-Path ([IO.Path]::GetTempPath()) ('micv-build-tests-' + [guid]::NewGuid().ToString())
New-Item -ItemType Directory -Path $TempFolder | Out-Null
try {
    Assert-That ($null -eq (Test-SupportedPython -Candidate (Join-Path $TempFolder 'missing.exe'))) 'missing executable is handled without a mandatory launcher'
    Assert-That ($null -eq (Test-SupportedPython -Candidate 'C:\Users\Example\AppData\Local\Microsoft\WindowsApps\python.exe')) 'unresolved Store placeholder does not validate as Python'
    foreach ($Case in @(@{Minor=11;Bits=64},@{Minor=12;Bits=32},@{Minor=15;Bits=64})) {
        $Stub = Join-Path $TempFolder ('python wrong ' + $Case.Minor + '-' + $Case.Bits + '.ps1')
        $Json = @{exe=$ResolvedPython;major=3;minor=$Case.Minor;bits=$Case.Bits;implementation='cpython';free_threaded=$false} | ConvertTo-Json -Compress
        Set-Content -LiteralPath $Stub -Value ('$global:LASTEXITCODE = 0; ' + "'" + $Json.Replace("'","''") + "'") -Encoding UTF8
        Assert-That ($null -eq (Test-SupportedPython -Candidate $Stub)) 'unsupported Python version or architecture is rejected'
    }
    foreach ($Minor in @(12,13,14)) {
        $Stub = Join-Path $TempFolder ('python supported ' + $Minor + '.ps1')
        $Json = @{exe=$ResolvedPython;major=3;minor=$Minor;bits=64;implementation='cpython';free_threaded=$false} | ConvertTo-Json -Compress
        Set-Content -LiteralPath $Stub -Value ('$global:LASTEXITCODE = 0; ' + "'" + $Json.Replace("'","''") + "'") -Encoding UTF8
        Assert-That ($null -ne (Test-SupportedPython -Candidate $Stub)) '3.12, 3.13 and 3.14 x64 are accepted'
    }
    $Explicit = Find-SupportedPython -Override $ResolvedPython -ProjectRoot $TempFolder
    Assert-That ($Explicit.Executable -eq $ResolvedPython) 'explicit interpreter override works'
    # Simulate a computer with python.exe on PATH and no py launcher.
    $script:ProbePython = $ResolvedPython
    function Get-Command {
        param($Name,$CommandType,$ErrorAction)
        if ($Name -eq 'python.exe') { [pscustomobject]@{Source=$script:ProbePython} }
    }
    try {
        $Automatic = Find-SupportedPython -ProjectRoot $TempFolder
        Assert-That ($Automatic.Executable -eq $ResolvedPython) 'automatic discovery works with python.exe and without py'
    } finally { Remove-Item Function:\Get-Command }
    $Failed = $false
    try { Find-SupportedPython -Override (Join-Path $TempFolder 'missing.exe') -ProjectRoot $TempFolder | Out-Null } catch { $Failed = $true }
    Assert-That $Failed 'invalid explicit interpreter has an actionable error'
    Write-Host "PowerShell compiler checks passed: $Count"
} finally {
    Remove-Item -LiteralPath $TempFolder -Recurse -Force
}
