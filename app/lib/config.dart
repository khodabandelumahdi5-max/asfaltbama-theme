/// تنظیمات اپ. بدون تغییر کد، هنگام ساخت با --dart-define عوض می‌شوند:
///
///   flutter build apk --dart-define=SITE_URL=https://ghetehfori.ir \
///     --dart-define=WHATSAPP=09121234567 --dart-define=PHONE=02100000000
library;

class AppConfig {
  /// نشانی سایت ووکامرس (بدون / آخر).
  static const siteUrl = String.fromEnvironment('SITE_URL', defaultValue: 'https://ghetehfori.ir');

  /// شماره واتس‌اپ کارشناس، مثلاً 09121234567. خالی = دکمه واتس‌اپ نمایش داده نمی‌شود.
  static const whatsapp = String.fromEnvironment('WHATSAPP');

  /// تلفن فروشگاه برای دکمه تماس.
  static const phone = String.fromEnvironment('PHONE');

  /// سفارش کالای موجود تا این ساعت «امروز ارسال» می‌شود؛ ۰ یعنی پیام ارسال نمایش داده نشود.
  static const shipCutoff = int.fromEnvironment('SHIP_CUTOFF', defaultValue: 14);
}
