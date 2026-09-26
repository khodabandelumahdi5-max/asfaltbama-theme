import 'package:flutter/material.dart';

import '../api.dart';
import '../config.dart';
import '../format.dart';
import '../links.dart';
import '../theme.dart';
import '../widgets/origin_card.dart';
import '../widgets/part_tile.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, this.api});

  /// برای تست می‌شود API ساختگی داد.
  final StoreApi? api;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late final StoreApi _api = widget.api ?? StoreApi();
  late Future<List<Part>> _parts = _api.popular();

  void _reload() => setState(() => _parts = _api.popular());

  void _search(String q) {
    if (q.trim().isNotEmpty) openInApp(SiteLinks.search(q.trim()));
  }

  void _openSupport() {
    final wa = SiteLinks.whatsapp('سلام، برای پیدا کردن قطعه راهنمایی می‌خواهم.');
    final tel = SiteLinks.phone();
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('گفتگو با کارشناس قطعه', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 17)),
              const SizedBox(height: 4),
              const Text('شماره فنی، مدل و سال خودرو را آماده داشته باشید.', style: TextStyle(color: Brand.muted)),
              const SizedBox(height: 16),
              if (wa != null) ...[
                FilledButton.icon(onPressed: () => openExternal(wa), icon: const Icon(Icons.chat_outlined), label: const Text('پیام در واتس‌اپ')),
                const SizedBox(height: 8),
              ],
              if (tel != null)
                OutlinedButton.icon(onPressed: () => openExternal(tel), icon: const Icon(Icons.call_outlined), label: const Text('تماس تلفنی')),
              if (wa == null && tel == null)
                const Text('شماره تماس هنوز در تنظیمات اپ وارد نشده است.', style: TextStyle(color: Brand.muted)),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final promise = deliveryPromise(DateTime.now(), AppConfig.shipCutoff);
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 12,
        title: Row(
          children: [
            Image.asset('assets/images/emblem.png', width: 38, height: 38, semanticLabel: 'نشان قطعه فوری'),
            const SizedBox(width: 10),
            const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('قطعه فوری', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18)),
                Text('داروخانه اتومبیل', style: TextStyle(fontSize: 11, color: Color(0xFFCBD5E1))),
              ],
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'سبد خرید',
            icon: const Icon(Icons.shopping_cart_outlined),
            onPressed: () => openInApp(SiteLinks.cart()),
          ),
          IconButton(
            tooltip: 'پشتیبانی',
            icon: const Icon(Icons.support_agent),
            onPressed: _openSupport,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => _reload(),
        child: ListView(
          padding: const EdgeInsets.only(bottom: 24),
          children: [
            if (promise != null) _PromiseStrip(text: promise),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              child: TextField(
                textInputAction: TextInputAction.search,
                onSubmitted: _search,
                decoration: const InputDecoration(
                  hintText: 'نام قطعه، شماره فنی یا OEM…',
                  prefixIcon: Icon(Icons.search),
                ),
              ),
            ),
            const SizedBox(height: 16),
            _InquiryCard(onSupport: _openSupport),
            const _SectionTitle('انتخاب بر اساس کشور سازنده خودرو'),
            SizedBox(
              height: 124,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: origins.length,
                separatorBuilder: (_, _) => const SizedBox(width: 10),
                itemBuilder: (_, i) => OriginCard(
                  origin: origins[i],
                  onTap: () => openInApp(SiteLinks.origin(origins[i].code)),
                ),
              ),
            ),
            _SectionTitle(
              'پرفروش‌ترین قطعات',
              action: TextButton(onPressed: () => openInApp(SiteLinks.shop()), child: const Text('همه قطعات')),
            ),
            FutureBuilder<List<Part>>(
              future: _parts,
              builder: (context, snap) {
                if (snap.connectionState != ConnectionState.done) {
                  return const Padding(
                    padding: EdgeInsets.all(32),
                    child: Center(child: CircularProgressIndicator()),
                  );
                }
                if (snap.hasError) {
                  return _ErrorBox(message: '${snap.error}', onRetry: _reload);
                }
                final parts = snap.data ?? const [];
                if (parts.isEmpty) {
                  return const Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('هنوز محصولی ثبت نشده است.', textAlign: TextAlign.center, style: TextStyle(color: Brand.muted)),
                  );
                }
                return Column(
                  children: [
                    for (final p in parts)
                      PartTile(part: p, onTap: () => openInApp(Uri.parse(p.permalink))),
                  ],
                );
              },
            ),
          ],
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: 0,
        onDestinationSelected: (i) {
          switch (i) {
            case 1:
              openInApp(SiteLinks.shop());
            case 2:
              openInApp(SiteLinks.garage());
            case 3:
              openInApp(SiteLinks.account());
          }
        },
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'خانه'),
          NavigationDestination(icon: Icon(Icons.grid_view_outlined), label: 'قطعات'),
          NavigationDestination(icon: Icon(Icons.directions_car_outlined), label: 'گاراژ من'),
          NavigationDestination(icon: Icon(Icons.person_outline), label: 'حساب من'),
        ],
      ),
    );
  }
}

class _PromiseStrip extends StatelessWidget {
  const _PromiseStrip({required this.text});
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Brand.ink2,
      padding: const EdgeInsets.symmetric(vertical: 9, horizontal: 16),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.local_shipping_outlined, color: Color(0xFFFDBA74), size: 20),
          const SizedBox(width: 8),
          Flexible(
            child: Text(text, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
          ),
        ],
      ),
    );
  }
}

/// استعلام با عکس کارت خودرو، شماره شاسی (VIN) یا خود قطعه — در واتس‌اپ،
/// که فرستادن عکس را خودش دارد.
class _InquiryCard extends StatelessWidget {
  const _InquiryCard({required this.onSupport});
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    final wa = SiteLinks.whatsapp('سلام، برای استعلام قطعه عکس کارت خودرو / شماره شاسی / قطعه را می‌فرستم.');
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Brand.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Row(
            children: [
              Icon(Icons.document_scanner_outlined, color: Brand.accent),
              SizedBox(width: 8),
              Expanded(
                child: Text('استعلام قطعه با عکس یا شماره شاسی', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
              ),
            ],
          ),
          const SizedBox(height: 6),
          const Text(
            'عکس کارت خودرو، شماره شاسی (VIN) یا خود قطعه را بفرستید تا کارشناس، قطعه درست را با شماره فنی پیدا کند.',
            style: TextStyle(color: Brand.muted, fontSize: 13, height: 1.7),
          ),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: wa != null ? () => openExternal(wa) : onSupport,
            icon: const Icon(Icons.photo_camera_outlined),
            label: Text(wa != null ? 'ارسال عکس در واتس‌اپ' : 'تماس با کارشناس'),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text, {this.action});
  final String text;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 22, 8, 10),
      child: Row(
        children: [
          Expanded(child: Text(text, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16, color: Brand.ink))),
          ?action,
        ],
      ),
    );
  }
}

class _ErrorBox extends StatelessWidget {
  const _ErrorBox({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const Icon(Icons.wifi_off_rounded, color: Brand.muted, size: 36),
          const SizedBox(height: 8),
          const Text('اتصال به فروشگاه برقرار نشد.', style: TextStyle(fontWeight: FontWeight.w700)),
          Text(message, style: const TextStyle(color: Brand.muted, fontSize: 12)),
          const SizedBox(height: 12),
          OutlinedButton(onPressed: onRetry, child: const Text('تلاش دوباره')),
        ],
      ),
    );
  }
}
