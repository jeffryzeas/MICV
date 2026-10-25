from __future__ import annotations
from decimal import Decimal, ROUND_HALF_UP, InvalidOperation
from datetime import date
import time
from .security import AccessError, verify_lease

def number(value, *, minimum='0', maximum='999999999.99') -> Decimal:
    try:
        value = Decimal(str(value))
        if not value.is_finite() or value < Decimal(minimum) or value > Decimal(maximum):
            raise ValueError()
        return value.quantize(Decimal('0.01'), rounding=ROUND_HALF_UP)
    except (InvalidOperation, ValueError) as exc:
        raise AccessError('Revise el importe o cantidad indicado.') from exc

def rounded(value, decimals=2):
    return value.quantize(Decimal('1').scaleb(-int(decimals)), rounding=ROUND_HALF_UP)

class Service:
    def __init__(self, store):
        self.store = store

    def claims(self, valid=False):
        return verify_lease(self.store.meta('lease', {}), self.store.meta('profile', {}), self.store.meta('device_uuid'), valid=valid)

    def guard(self, scope):
        if self.store.meta('blocked', False):
            raise AccessError('El servidor desactivó este acceso. Sus datos se conservan; contacte al administrador.')
        now = time.time()
        if now + 300 < self.store.meta('last_clock', 0):
            raise AccessError('La fecha del equipo retrocedió. Corrija la hora y conecte el programa.')
        claims = self.claims(valid=True)
        if scope not in claims['scopes']:
            raise AccessError('Su licencia o usuario no permite esta acción.')
        self.store.set_meta('last_clock', max(now, self.store.meta('last_clock', 0)))
        return claims

    def allowed(self, scope):
        try:
            return scope in self.claims()['scopes']
        except AccessError:
            return False

    def effective(self, entity):
        values = {str(r['id']): dict(r, data=dict(r['data'])) for r in self.store.rows(entity)}
        for op in self.store.operations(include_accepted=True):
            local = 'local:' + op['uuid']
            key = str(op['remote_id']) if op['status'] == 'accepted' else local
            if entity == 'clients' and op['type'] == 'client.create' and key not in values:
                values[key] = {'id': key, 'revision': None, 'data': op['data']['client'], 'local': op['status'] != 'accepted'}
            elif entity == 'clients' and op['type'] == 'client.update' and op['status'] != 'accepted':
                row = values.get(str(op['data']['id']))
                if row:
                    row['data'].update(op['data']['client']); row['local'] = True
            elif entity == 'ledger' and op['type'] == 'ledger.create' and key not in values:
                values[key] = {'id': key, 'data': dict(op['data'], estado='activo', origen='manual'), 'local': op['status'] != 'accepted'}
        if entity == 'ledger' and self.store.meta('business', {}).get('contabilidad_pos'):
            fresh_at = self.store.meta('cache_time:ledger', 0)
            for op in self.store.operations(include_accepted=True):
                if op['type'] != 'sale.create' or (op['status'] == 'accepted' and (op['ack_at'] or 0) <= fresh_at):
                    continue
                if op['status'] == 'accepted' and any(str(r['data'].get('venta_id')) == str(op['remote_id']) for r in values.values()):
                    continue
                values['sale:' + op['uuid']] = {'id': 'sale:' + op['uuid'], 'local': True, 'data': {'fecha': op['occurred_at'][:10], 'tipo': 'ingreso', 'categoria': 'Ventas POS', 'concepto': 'Venta local ' + op['uuid'][:8], 'monto': op['data']['total'], 'moneda': op['data']['moneda'], 'estado': 'activo', 'origen': 'pos'}}
        if entity == 'products':
            fresh_at = self.store.meta('cache_time:products', 0)
            for op in self.store.operations(include_accepted=True):
                if op['type'] != 'sale.create' or (op['status'] == 'accepted' and (op['ack_at'] or 0) <= fresh_at):
                    continue
                for item in op['data']['items']:
                    row = values.get(str(item['producto_id']))
                    if row and row['data'].get('controla_stock'):
                        row['data']['stock'] = str(Decimal(str(row['data']['stock'])) - Decimal(str(item['cantidad'])))
        return list(values.values())

    def client(self, data, existing=None):
        self.guard('clientes.editar')
        catalogs = self.store.meta('catalogs', {})
        country = catalogs.get('countries', {}).get(data.get('pais'))
        if not country or data.get('tipo_documento') not in country['documentos']:
            raise AccessError('Seleccione el país emisor y un tipo de documento de ese país.')
        nationality = str(data.get('nacionalidad') or '').strip()
        known = {n[k] for n in catalogs.get('nationalities', []) for k in ('m', 'f')}
        if nationality not in known:
            raise AccessError('Seleccione la nacionalidad del catálogo.')
        if data.get('pais_residencia') not in catalogs.get('countries', {}) or data.get('pais_residencia') == 'OT':
            raise AccessError('Seleccione el país de residencia actual.')
        for key in ('primer_nombre', 'primer_apellido', 'numero_documento'):
            if not str(data.get(key, '')).strip():
                raise AccessError('Complete nombre, apellido y documento.')
        national_country = next((n['c'] for n in catalogs['nationalities'] if nationality in (n['m'], n['f'])), None)
        if national_country != data['pais_residencia'] and len(str(data.get('autorizacion_laboral') or '').strip()) < 3:
            raise AccessError('Indique su situación laboral en el país de residencia.')
        if existing:
            if existing.get('local') or not existing.get('revision'):
                raise AccessError('Sincronice primero la ficha pendiente antes de editarla otra vez.')
            op = self.store.queue('client.update', {'id': existing['id'], 'revision': existing['revision'], 'client': data})
        else:
            if any(r['data'].get('tipo_documento') == data['tipo_documento'] and r['data'].get('numero_documento') == data['numero_documento'] for r in self.effective('clients')):
                raise AccessError('Este documento ya aparece en los clientes locales.')
            op = self.store.queue('client.create', {'client': data})
        return op

    def ledger(self, data):
        self.guard('contabilidad.registrar')
        if data.get('tipo') not in ('ingreso', 'gasto') or not data.get('concepto', '').strip() or not data.get('categoria', '').strip():
            raise AccessError('Complete tipo, concepto y categoría.')
        try:
            date.fromisoformat(data['fecha'])
        except (ValueError, KeyError) as exc:
            raise AccessError('Seleccione una fecha válida.') from exc
        data = dict(data, monto=str(number(data['monto'], minimum='0.01')), moneda=self.store.meta('business')['moneda'])
        return self.store.queue('ledger.create', data)

    def cash(self):
        current = self.store.meta('cash')
        for op in self.store.operations():
            if op['type'] == 'cash.open':
                current = {'id': 'local:' + op['uuid'], 'monto_inicial': op['data']['monto_inicial'], 'esperado': op['data']['monto_inicial']}
            elif op['type'] == 'cash.close':
                current = None
        return current

    def _pos_ready(self):
        if any(op['status'] == 'conflict' and op['type'].startswith(('cash.', 'sale.')) for op in self.store.operations()):
            raise AccessError('Hay operaciones de caja o ventas que requieren revisión en Sincronización.')

    def open_cash(self, amount):
        self.guard('pos.caja'); self._pos_ready()
        if self.cash():
            raise AccessError('Ya hay una caja abierta.')
        return self.store.queue('cash.open', {'monto_inicial': str(number(amount))})

    def close_cash(self, amount):
        self.guard('pos.caja.cerrar'); self._pos_ready()
        cash = self.cash()
        if not cash:
            raise AccessError('No hay una caja abierta.')
        return self.store.queue('cash.close', {'caja_id': cash['id'], 'efectivo_contado': str(number(amount))})

    def quote(self, cart):
        cfg = self.store.meta('business', {})
        catalog = {str(p['id']): p['data'] for p in self.effective('products')}
        if not cart:
            raise AccessError('Agregue productos a la venta.')
        combined = {}
        for item in cart:
            key = str(item['producto_id'])
            combined[key] = combined.get(key, Decimal(0)) + number(item['cantidad'], minimum='0.01', maximum='999999')
        total = Decimal(0); lines = []
        for key, qty in combined.items():
            product = catalog.get(key)
            if not product:
                raise AccessError('Un producto ya no está disponible. Sincronice el catálogo.')
            if product.get('controla_stock') and qty > Decimal(str(product['stock'])):
                raise AccessError('No hay existencia local suficiente de ' + product['nombre'])
            price = number(product['precio'])
            gross = rounded(price * qty)
            rate = Decimal(0) if product.get('exento') else Decimal(str(product.get('impuesto_tasa') if product.get('impuesto_tasa') is not None else cfg['impuesto_tasa']))
            value = rounded(gross if cfg['precios_con_impuesto'] else gross * (1 + rate / 100), cfg['decimales'])
            total += value
            lines.append({'producto_id': int(key), 'cantidad': str(qty), 'precio': str(price), 'nombre': product['nombre'], 'total': str(value)})
        return lines, rounded(total, cfg['decimales'])

    def sale(self, cart, payments, client_id=None):
        self.guard('pos.vender'); self._pos_ready()
        cash = self.cash()
        if not cash:
            raise AccessError('Abra una caja antes de vender.')
        lines, total = self.quote(cart)
        methods = {m['clave'] for m in self.store.meta('business')['metodos']}
        valid = []
        for payment in payments:
            if payment['metodo'] not in methods:
                raise AccessError('El método de pago no está activo.')
            valid.append(dict(payment, monto=str(number(payment['monto'], minimum='0.01'))))
        paid = sum((Decimal(p['monto']) for p in valid), Decimal(0))
        other = sum((Decimal(p['monto']) for p in valid if p['metodo'] != 'efectivo'), Decimal(0))
        if paid < total or other > total or not valid:
            raise AccessError('Revise el pago: debe cubrir el total y solo efectivo puede generar cambio.')
        if client_id:
            row = next((r for r in self.effective('clients') if str(r['id']) == str(client_id)), None)
            if not row:
                raise AccessError('El cliente no está en el catálogo local.')
            if str(client_id).startswith('local:'):
                original = next((o for o in self.store.operations(include_accepted=True) if 'local:' + o['uuid'] == str(client_id)), None)
                if original and original['status'] == 'accepted':
                    client_id = original['remote_id']
        return self.store.queue('sale.create', {'caja_id': cash['id'], 'cliente_id': client_id, 'items': lines, 'pagos': valid, 'total': str(total), 'moneda': self.store.meta('business')['moneda'], 'cambio_local': str(paid - total)})
