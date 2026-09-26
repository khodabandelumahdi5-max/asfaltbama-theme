import 'package:url_launcher/url_launcher.dart';

import 'config.dart';

/// صفحه‌های سایت (سبد، حساب، گاراژ) فعلاً در مرورگر داخل اپ باز می‌شوند.
class SiteLinks {
  static Uri page(String path, [Map<String, String>? query]) =>
      Uri.parse('${AppConfig.siteUrl}$path').replace(queryParameters: query);

  static Uri shop([Map<String, String>? query]) => page('/shop/', query);
  static Uri search(String q) => page('/', {'s': q, 'post_type': 'product'});
  static Uri origin(String code) => shop({'yf_origin': code});
  static Uri account() => page('/my-account/');
  static Uri garage() => page('/my-account/garage/');
  static Uri cart() => page('/cart/');

  /// 0912… → https://wa.me/98912…?text=…
  static Uri? whatsapp(String text) {
    var n = AppConfig.whatsapp.replaceAll(RegExp(r'\D'), '');
    if (n.isEmpty) return null;
    if (n.startsWith('0')) n = '98${n.substring(1)}';
    return Uri.parse('https://wa.me/$n').replace(queryParameters: {'text': text});
  }

  static Uri? phone() {
    final n = AppConfig.phone.replaceAll(RegExp(r'[^0-9+]'), '');
    return n.isEmpty ? null : Uri(scheme: 'tel', path: n);
  }
}

Future<bool> openInApp(Uri uri) => launchUrl(uri, mode: LaunchMode.inAppBrowserView);

Future<bool> openExternal(Uri uri) => launchUrl(uri, mode: LaunchMode.externalApplication);
