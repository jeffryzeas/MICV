#define MyAppName "Mi CV Escritorio"
#define MyAppVersion "1.0.0"
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
Filename: "{app}\MiCVDesktop.exe"; Description: "Abrir Mi CV Escritorio"; Flags: nowait postinstall skipifsilent
; Los datos en %LOCALAPPDATA%\EquipoJZ\MiCVDesktop NO se eliminan al desinstalar.
