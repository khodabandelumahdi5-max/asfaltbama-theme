import 'package:flutter/material.dart';

import '../api.dart';
import '../format.dart';
import '../theme.dart';

class PartTile extends StatelessWidget {
  const PartTile({super.key, required this.part, required this.onTap});
  final Part part;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = part;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 5),
      child: Material(
        color: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: const BorderSide(color: Brand.line)),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: SizedBox(
                    width: 76,
                    height: 76,
                    child: p.image != null
                        ? Image.network(p.image!, fit: BoxFit.cover, errorBuilder: (_, _, _) => const _Placeholder())
                        : const _Placeholder(),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(p.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, height: 1.5)),
                      if (p.vehicles.isNotEmpty)
                        Text(p.vehicles.join(' / '), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Brand.muted, fontSize: 12)),
                      if (p.partNumber.isNotEmpty)
                        // شماره فنی با ارقام لاتین و چپ‌به‌راست، مثل کاتالوگ سازنده.
                        Text(p.partNumber, textDirection: TextDirection.ltr, style: const TextStyle(color: Brand.muted, fontSize: 11, letterSpacing: .5)),
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          Text(toman(p.price, minorUnit: p.minorUnit), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: Brand.ink)),
                          if (p.onSale && p.regularPrice != p.price) ...[
                            const SizedBox(width: 8),
                            Text(
                              toman(p.regularPrice, minorUnit: p.minorUnit, suffix: ''),
                              style: const TextStyle(decoration: TextDecoration.lineThrough, color: Brand.muted, fontSize: 12),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 2),
                      _StockNote(part: p),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// وضعیت موجودی واقعی انبار؛ «فقط N عدد» فقط وقتی ووکامرس موجودی را کم اعلام کند.
class _StockNote extends StatelessWidget {
  const _StockNote({required this.part});
  final Part part;

  @override
  Widget build(BuildContext context) {
    final (text, color) = !part.inStock
        ? ('ناموجود', Brand.danger)
        : part.lowStock != null
            ? ('فقط ${faDigits(part.lowStock!)} عدد در انبار', Brand.accentInk)
            : ('موجود', Brand.ok);
    return Text(text, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w700));
  }
}

class _Placeholder extends StatelessWidget {
  const _Placeholder();

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Brand.surface,
      padding: const EdgeInsets.all(14),
      child: Opacity(opacity: .45, child: Image.asset('assets/images/emblem.png')),
    );
  }
}
