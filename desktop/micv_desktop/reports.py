"""Reportes locales con texto vectorial, encabezados repetidos y Excel tipado."""
from pathlib import Path
from datetime import datetime
from decimal import Decimal,InvalidOperation
from xml.sax.saxutils import escape
import re
ASSETS=Path(__file__).resolve().parent.parent/'assets'
NUMERIC={'Monto','Total','Precio','Cantidad','Existencia','Importe'}

def numeric(header,value):
    text=str(value).strip()
    if header in NUMERIC and re.fullmatch(r'-?\d+(?:\.\d+)?',text):
        try:return Decimal(text)
        except InvalidOperation:pass
    return None

def excel_report(path,title,business,headers,rows,logo=None):
    from openpyxl import Workbook
    from openpyxl.styles import Font,PatternFill,Alignment,Border,Side
    from openpyxl.utils import get_column_letter
    from openpyxl.worksheet.pagebreak import Break
    book=Workbook();sheet=book.active;sheet.title=title[:31];n=len(headers)
    sheet.merge_cells(start_row=1,start_column=1,end_row=1,end_column=n);sheet.cell(1,1,title).font=Font(name='Calibri',size=20,bold=True,color='143F70')
    sheet.merge_cells(start_row=2,start_column=1,end_row=2,end_column=n);sheet.cell(2,1,str(business)+' · '+datetime.now().strftime('%d/%m/%Y %H:%M')).data_type='s'
    sheet.merge_cells(start_row=3,start_column=1,end_row=3,end_column=n);sheet.cell(3,1,f'{len(rows)} registros · Datos locales del equipo').font=Font(color='64748B',italic=True)
    sheet.append(headers);sheet.row_dimensions[1].height=35;sheet.row_dimensions[2].height=28
    for values in rows:
        sheet.append(['']*n);row=sheet.max_row
        for j,(header,value) in enumerate(zip(headers,values),1):
            cell=sheet.cell(row,j);number=numeric(header,value)
            if number is None:cell.value=str(value or '');cell.data_type='s'
            else:cell.value=number;cell.number_format='#,##0.00;[Red]-#,##0.00'
            cell.font=Font(name='Calibri',size=11,color='203247');cell.alignment=Alignment(vertical='top',wrap_text=True)
            if row%2:cell.fill=PatternFill('solid',fgColor='EFF4FA')
        sheet.row_dimensions[row].height=min(100,22+14*max((len(str(v))//40 for v in values),default=0))
    for cell in sheet[4]:cell.font=Font(name='Calibri',bold=True,color='FFFFFF');cell.fill=PatternFill('solid',fgColor='143F70');cell.alignment=Alignment(wrap_text=True,vertical='center')
    sheet.row_dimensions[4].height=30
    for j,h in enumerate(headers,1):sheet.column_dimensions[get_column_letter(j)].width=14 if h in NUMERIC else (36 if h in ('Nombre','Producto','Concepto','Detalle') else 24)
    sheet.freeze_panes='A5';sheet.auto_filter.ref=f'A4:{get_column_letter(n)}{max(4,sheet.max_row)}';sheet.print_title_rows='1:4'
    sheet.sheet_properties.pageSetUpPr.fitToPage=True;sheet.page_setup.orientation='landscape' if n>4 else 'portrait';sheet.page_setup.paperSize=sheet.PAPERSIZE_A4;sheet.page_setup.fitToWidth=1;sheet.page_setup.fitToHeight=0
    sheet.oddFooter.center.text='Página &P de &N';sheet.oddFooter.left.text='Mi CV · Datos locales';sheet.print_options.horizontalCentered=True
    if logo and Path(logo).is_file():
        from openpyxl.drawing.image import Image
        image=Image(str(logo));ratio=min(80/image.width,40/image.height);image.width*=ratio;image.height*=ratio;sheet.add_image(image,f'{get_column_letter(n)}1')
    book.save(path)

def pdf_report(path,title,business,headers,rows,orientation='auto',logo=None,note='Datos locales del equipo',summary=None):
    from reportlab.lib import colors
    from reportlab.lib.pagesizes import A4,landscape
    from reportlab.lib.styles import ParagraphStyle
    from reportlab.lib.units import mm
    from reportlab.pdfbase import pdfmetrics
    from reportlab.pdfbase.ttfonts import TTFont
    from reportlab.platypus import SimpleDocTemplate,Paragraph,Spacer,LongTable,TableStyle,Image,Table
    for name,file in [('MiCV','DejaVuSans.ttf'),('MiCVBold','DejaVuSans-Bold.ttf')]:
        if name not in pdfmetrics.getRegisteredFontNames():pdfmetrics.registerFont(TTFont(name,str(ASSETS/'fonts'/file)))
    wide=orientation=='landscape' or (orientation=='auto' and len(headers)>4);page=landscape(A4) if wide else A4
    doc=SimpleDocTemplate(str(path),pagesize=page,rightMargin=14*mm,leftMargin=14*mm,topMargin=14*mm,bottomMargin=18*mm,title=title,author=str(business))
    normal=ParagraphStyle('cell',fontName='MiCV',fontSize=8,leading=11,textColor=colors.HexColor('#203247'),splitLongWords=True)
    head=ParagraphStyle('head',parent=normal,fontName='MiCVBold',textColor=colors.white)
    title_style=ParagraphStyle('title',parent=normal,fontName='MiCVBold',fontSize=20,leading=25,textColor=colors.HexColor('#143F70'))
    def para(value,style=normal):return Paragraph(escape(str(value or '')).replace('\n','<br/>'),style)
    top=[para(title,title_style),Spacer(1,4*mm),para(business),Spacer(1,2*mm),para(datetime.now().strftime('%d/%m/%Y %H:%M')+f' · {len(rows)} registros')]
    if logo and Path(logo).is_file():
        image=Image(str(logo));ratio=min(28*mm/image.imageWidth,18*mm/image.imageHeight);image.drawWidth=image.imageWidth*ratio;image.drawHeight=image.imageHeight*ratio
        story=[Table([[top,image]],colWidths=[doc.width-32*mm,32*mm],style=[('VALIGN',(0,0),(-1,-1),'TOP')])]
    else:story=top
    story += [Spacer(1,5*mm)]
    weights=[.8 if h in NUMERIC else (2 if h in ('Nombre','Producto','Concepto','Detalle') else 1.3) for h in headers];widths=[doc.width*w/sum(weights) for w in weights]
    data=[[para(h,head) for h in headers]]+[[para(v) for v in row] for row in rows]
    if not rows:data.append([para('Sin registros para mostrar.')]+['']*(len(headers)-1))
    table=LongTable(data,colWidths=widths,repeatRows=1,splitByRow=1,splitInRow=1,hAlign='LEFT')
    style=[('BACKGROUND',(0,0),(-1,0),colors.HexColor('#143F70')),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),7),('RIGHTPADDING',(0,0),(-1,-1),7),('TOPPADDING',(0,0),(-1,-1),7),('BOTTOMPADDING',(0,0),(-1,-1),7),('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.white,colors.HexColor('#EFF4FA')]),('LINEBELOW',(0,0),(-1,0),.5,colors.HexColor('#143F70')),('LINEBELOW',(0,1),(-1,-1),.25,colors.HexColor('#D5DFEB'))]
    table.setStyle(TableStyle(style));story.append(table)
    if summary:story += [Spacer(1,5*mm),para(summary,title_style)]
    def footer(canvas,doc):
        canvas.saveState();canvas.setStrokeColor(colors.HexColor('#CBD5E1'));canvas.line(14*mm,13*mm,page[0]-14*mm,13*mm);canvas.setFont('MiCV',7);canvas.setFillColor(colors.HexColor('#64748B'));canvas.drawString(14*mm,9*mm,note[:110]);canvas.drawRightString(page[0]-14*mm,9*mm,f'Página {doc.page}');canvas.restoreState()
    doc.build(story,onFirstPage=footer,onLaterPages=footer)
