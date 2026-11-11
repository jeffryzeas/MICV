#define MyAppName "Mi CV Escritorio"
#ifndef MyAppVersion
#define MyAppVersion "2.0.0-beta.6"
#endif
[Setup]
AppId={{D783DB38-37CE-47E0-BA4A-621A168F7D09}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher=Equipo JZ
AppPublisherURL=https://jz.com.ni
DefaultDirName={localappdata}\Programs\EquipoJZ\MiCVDesktop
DefaultGroupName=Equipo JZ
PrivilegesRequired=lowest
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
MinVersion=10.0.19041
OutputDir=..\dist\installer
OutputBaseFilename=MiCVDesktop-Setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
SetupIconFile=..\assets\micv.ico
UninstallDisplayIcon={app}\MiCVDesktop.exe
CloseApplications=yes
[Languages]
Name: "spanish"; MessagesFile: "compiler:Languages\Spanish.isl"
[Tasks]
Name: "desktopicon"; Description: "Crear acceso directo en el escritorio"; GroupDescription: "Accesos directos:"; Flags: unchecked
[Files]
Source: "..\dist\MiCVDesktop\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs
[Icons]
Name: "{group}\Mi CV Escritorio"; Filename: "{app}\MiCVDesktop.exe"
Name: "{autodesktop}\Mi CV Escritorio"; Filename: "{app}\MiCVDesktop.exe"; Tasks: desktopicon
[Run]
Filename: "{app}\_internal\runtime-bundle\vc_redist.x64.exe"; Parameters: "/install /quiet /norestart"; Flags: shellexec waituntilterminated; Check: FileExists(ExpandConstant('{app}\_internal\runtime-bundle\vc_redist.x64.exe')) and not RegValueExists(HKLM64, 'SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64', 'Installed')

Filename: "{app}\MiCVDesktop.exe"; Description: "Abrir Mi CV Escritorio"; Flags: nowait postinstall skipifsilent
; Los datos en %LOCALAPPDATA%\EquipoJZ\MiCVDesktop NO se eliminan al desinstalar.
