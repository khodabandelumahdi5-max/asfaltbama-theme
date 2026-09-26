import 'package:flutter_test/flutter_test.dart';
import 'package:ghateh_foori/format.dart';

void main() {
  test('Persian digits', () {
    expect(faDigits(1405), '۱۴۰۵');
    expect(faDigits('206 تیپ 5'), '۲۰۶ تیپ ۵');
  });

  test('toman price', () {
    expect(toman('18500000'), '۱۸,۵۰۰,۰۰۰ تومان');
    expect(toman('950'), '۹۵۰ تومان');
    expect(toman('1850000', minorUnit: 2), '۱۸,۵۰۰ تومان');
  });

  group('delivery promise', () {
    // 2026-09-26 is a Saturday.
    test('before cut-off: today', () {
      expect(deliveryPromise(DateTime(2026, 9, 26, 10), 14), 'تا ساعت ۱۴ سفارش دهید، امروز ارسال می‌شود');
    });
    test('after cut-off: tomorrow', () {
      expect(deliveryPromise(DateTime(2026, 9, 26, 15), 14), 'سفارش امروز، فردا ارسال می‌شود');
    });
    test('Thursday evening and Friday: Saturday', () {
      expect(deliveryPromise(DateTime(2026, 10, 1, 16), 14), 'سفارش امروز، شنبه ارسال می‌شود');
      expect(deliveryPromise(DateTime(2026, 10, 2, 9), 14), 'سفارش امروز، شنبه ارسال می‌شود');
    });
    test('disabled', () => expect(deliveryPromise(DateTime(2026, 9, 26, 10), 0), isNull));
  });
}
