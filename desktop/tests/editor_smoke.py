import sys
from pathlib import Path
import tempfile
import time

desktop = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(desktop))
from PySide6.QtCore import QCoreApplication, QEvent, QUrl
from PySide6.QtWidgets import QApplication
from micv_desktop.gui import MainWindow
from micv_desktop.storage import Store

app = QApplication([])
profile = tempfile.TemporaryDirectory()
main = MainWindow(Store(Path(profile.name)), start_timer=False)
main.service.guard = lambda permission: {}
main.open_editor()
window = main.editor_window
engine = window.engine

def until(condition):
    deadline = time.monotonic() + 12
    while not condition():
        app.processEvents()
        if time.monotonic() > deadline:
            raise AssertionError('Timed out in the embedded editor')
        time.sleep(.01)

def evaluate(script):
    result = []
    window.tabs.widget(0).page().runJavaScript(script, result.append)
    until(lambda: bool(result))
    return result[0]

until(lambda: evaluate("typeof PDFLib === 'object' && typeof document.getElementById('doc-upload').onclick === 'function'"))
assert engine.allows_file(window.view.url())
assert not engine.allows_file(QUrl.fromLocalFile(str(Path(__file__).resolve())))
evaluate("""
(() => {
    const canvas=document.createElement('canvas');
    canvas.width=360;canvas.height=220;
    const ctx=canvas.getContext('2d');ctx.fillStyle='#fafafa';ctx.fillRect(0,0,360,220);
    ctx.fillStyle='#234567';ctx.fillRect(25,25,300,40);
    canvas.toBlob(blob=>{
        const transfer=new DataTransfer();
        transfer.items.add(new File([blob],'fixture.png',{type:'image/png'}));
        const files=document.getElementById('doc-files');
        files.files=transfer.files;files.dispatchEvent(new Event('change'));
    }, 'image/png');
})()
""")
until(lambda: evaluate("!document.getElementById('doc-pdf').disabled"))
assert evaluate("document.getElementById('doc-canvas').width") == 360
evaluate("document.getElementById('doc-rotate-right').click()")
until(lambda: evaluate("document.getElementById('doc-canvas').width===220 && document.getElementById('doc-canvas').height===360"))

with tempfile.TemporaryDirectory() as folder:
    output=Path(folder)/'copy.pdf'
    window.profile.downloadRequested.disconnect(window.download)
    def save(request):
        request.setDownloadDirectory(folder)
        request.setDownloadFileName(output.name)
        request.accept()
    window.profile.downloadRequested.connect(save)
    evaluate("document.getElementById('doc-pdf').click()")
    until(lambda: output.is_file() and output.stat().st_size > 100)
    assert output.read_bytes().startswith(b'%PDF-')
    until(lambda: evaluate("!document.getElementById('doc-print').disabled"))
    evaluate("document.getElementById('doc-print').click()")
    try:
        until(lambda:window.tabs.count()==2 and window.tabs.widget(1).url().scheme()=='blob')
    except AssertionError:
        print('tabs',window.tabs.count(),[window.tabs.widget(i).url().toString() for i in range(window.tabs.count())])
        print('message',evaluate("document.body.innerText.slice(-450)"))
        raise
window.close();window.deleteLater()
main.editor_window = None
main.close();main.deleteLater()
profile.cleanup()
QCoreApplication.sendPostedEvents(None,QEvent.Type.DeferredDelete)
print('PASS: embedded editor loads assets, reads PNG, exports PDF and opens PDF in internal tab')
