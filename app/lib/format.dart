/// قالب‌بندی فارسی: ارقام، قیمت تومان و وعده ارسال.
library;

const _fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

/// «18500000» → «۱۸۵۰۰۰۰۰».
String faDigits(Object value) =>
    value.toString().replaceAllMapped(RegExp(r'[0-9]'), (m) => _fa[int.parse(m[0]!)]);

/// قیمت Store API (واحد کوچک + تعداد رقم اعشار) → «۱۸,۵۰۰,۰۰۰ تومان».
String toman(String minorUnits, {int minorUnit = 0, String suffix = ' تومان'}) {
  final raw = int.tryParse(minorUnits) ?? 0;
  var amount = raw;
  for (var i = 0; i < minorUnit; i++) {
    amount ~/= 10;
  }
  final digits = amount.toString();
  final grouped = StringBuffer();
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) grouped.write(',');
    grouped.write(digits[i]);
  }
  return faDigits(grouped.toString()) + suffix;
}

/// مثل سایت: قبل از ساعت پایان، «امروز ارسال»؛ بعد از آن فردا (جمعه تعطیل).
String? deliveryPromise(DateTime now, int cutoff) {
  if (cutoff <= 0) return null;
  final isFriday = now.weekday == DateTime.friday;
  if (!isFriday && now.hour < cutoff) {
    return 'تا ساعت ${faDigits(cutoff)} سفارش دهید، امروز ارسال می‌شود';
  }
  final next = (now.weekday == DateTime.thursday && now.hour >= cutoff) || isFriday ? 'شنبه' : 'فردا';
  return 'سفارش امروز، $next ارسال می‌شود';
}
