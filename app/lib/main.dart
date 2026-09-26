import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'screens/home_screen.dart';
import 'theme.dart';

void main() => runApp(const GhatehFooriApp());

class GhatehFooriApp extends StatelessWidget {
  const GhatehFooriApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'قطعه فوری | داروخانه اتومبیل',
      theme: buildTheme(),
      // زبان فارسی: کل اپ راست‌به‌چپ می‌شود و متن دکمه‌ها و تاریخ‌های
      // خود فلاتر هم فارسی است.
      locale: const Locale('fa', 'IR'),
      supportedLocales: const [Locale('fa', 'IR')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      home: const HomeScreen(),
    );
  }
}
