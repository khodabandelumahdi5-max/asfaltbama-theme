import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ghateh_foori/api.dart';
import 'package:ghateh_foori/screens/home_screen.dart';
import 'package:ghateh_foori/theme.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

/// The page's vertical list (the country row scrolls sideways).
final _page = find.byType(Scrollable).first;

void main() {
  final sample = [
    {
      'id': 72,
      'name': 'چراغ جلو راست چری تیگو ۷',
      'permalink': 'https://ghetehfori.ir/product/x/',
      'sku': 'HL-TG7R',
      'on_sale': false,
      'is_in_stock': true,
      'low_stock_remaining': 2,
      'prices': {'price': '18500000', 'regular_price': '18500000', 'currency_minor_unit': 0},
      'images': [],
      'extensions': {
        'yadak': {'part_number': 'T15-4421020', 'oem_numbers': [], 'vehicles': ['چری تیگو ۷']},
      },
    },
  ];

  testWidgets('home shows popular parts from the Store API', (tester) async {
    final client = MockClient((req) async {
      expect(req.url.path, '/wp-json/wc/store/v1/products');
      return http.Response.bytes(utf8.encode(jsonEncode(sample)), 200);
    });
    await tester.pumpWidget(MaterialApp(
      theme: buildTheme(),
      home: Directionality(textDirection: TextDirection.rtl, child: HomeScreen(api: StoreApi(client: client))),
    ));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('چراغ جلو راست چری تیگو ۷'), 300, scrollable: _page);
    expect(find.text('۱۸,۵۰۰,۰۰۰ تومان'), findsOneWidget);
    expect(find.text('فقط ۲ عدد در انبار'), findsOneWidget);
    expect(find.text('T15-4421020'), findsOneWidget);
  });

  testWidgets('server error shows retry', (tester) async {
    final client = MockClient((_) async => http.Response('oops', 500));
    await tester.pumpWidget(MaterialApp(home: HomeScreen(api: StoreApi(client: client))));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('تلاش دوباره'), 300, scrollable: _page);
    expect(find.text('اتصال به فروشگاه برقرار نشد.'), findsOneWidget);
  });
}
