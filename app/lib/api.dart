import 'dart:convert';

import 'package:http/http.dart' as http;

import 'config.dart';

/// محصول از Store API عمومی ووکامرس (بدون کلید). شماره فنی و خودروها را
/// افزونه Yadak Core زیر extensions.yadak اضافه می‌کند.
class Part {
  Part({
    required this.id,
    required this.name,
    required this.permalink,
    required this.price,
    required this.regularPrice,
    required this.minorUnit,
    required this.onSale,
    required this.inStock,
    required this.lowStock,
    required this.image,
    required this.partNumber,
    required this.vehicles,
  });

  final int id;
  final String name;
  final String permalink;
  final String price;
  final String regularPrice;
  final int minorUnit;
  final bool onSale;
  final bool inStock;

  /// تعداد باقی‌مانده، فقط وقتی موجودی کم است (از خود انبار ووکامرس).
  final int? lowStock;
  final String? image;
  final String partNumber;
  final List<String> vehicles;

  factory Part.fromJson(Map<String, dynamic> j) {
    final prices = (j['prices'] as Map?) ?? const {};
    final images = (j['images'] as List?) ?? const [];
    final yadak = ((j['extensions'] as Map?)?['yadak'] as Map?) ?? const {};
    return Part(
      id: j['id'] as int,
      name: _plain(j['name'] as String? ?? ''),
      permalink: j['permalink'] as String? ?? AppConfig.siteUrl,
      price: '${prices['price'] ?? '0'}',
      regularPrice: '${prices['regular_price'] ?? '0'}',
      minorUnit: (prices['currency_minor_unit'] as int?) ?? 0,
      onSale: j['on_sale'] == true,
      inStock: j['is_in_stock'] != false,
      lowStock: j['low_stock_remaining'] as int?,
      image: images.isNotEmpty ? ((images.first as Map)['thumbnail'] ?? (images.first as Map)['src']) as String? : null,
      partNumber: (yadak['part_number'] as String?) ?? (j['sku'] as String? ?? ''),
      vehicles: ((yadak['vehicles'] as List?) ?? const []).cast<String>(),
    );
  }

  /// نام محصول ممکن است موجودیت HTML داشته باشد (مثلاً &#8211;).
  static String _plain(String s) => s
      .replaceAll('&amp;', '&')
      .replaceAll('&#8211;', '–')
      .replaceAll('&#8220;', '«')
      .replaceAll('&#8221;', '»')
      .replaceAll(RegExp(r'<[^>]*>'), '');
}

class StoreApi {
  StoreApi({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;

  Future<List<Part>> popular({int count = 10}) async {
    final uri = Uri.parse('${AppConfig.siteUrl}/wp-json/wc/store/v1/products')
        .replace(queryParameters: {'per_page': '$count', 'orderby': 'popularity', 'order': 'desc'});
    final res = await _client.get(uri).timeout(const Duration(seconds: 15));
    if (res.statusCode != 200) {
      throw StoreApiException('خطای سرور (${res.statusCode})');
    }
    final data = jsonDecode(utf8.decode(res.bodyBytes));
    if (data is! List) throw StoreApiException('پاسخ نامعتبر');
    return data.map((e) => Part.fromJson(e as Map<String, dynamic>)).toList();
  }
}

class StoreApiException implements Exception {
  StoreApiException(this.message);
  final String message;
  @override
  String toString() => message;
}
