import 'package:flutter/material.dart';

import '../theme.dart';

class Origin {
  const Origin(this.code, this.label, this.flag, this.makes);

  /// همان کد مبدأ فیلتر سایت (yf_origin).
  final String code;
  final String label;
  final String flag;
  final String makes;
}

const origins = [
  Origin('cn', 'چین', '🇨🇳', 'چری، ام‌وی‌ام، جک'),
  Origin('jp', 'ژاپن', '🇯🇵', 'تویوتا، نیسان، مزدا'),
  Origin('kr', 'کره', '🇰🇷', 'هیوندای، کیا'),
  Origin('ir', 'ایران', '🇮🇷', 'ایران‌خودرو، سایپا'),
  Origin('eu', 'آلمان و اروپا', '🇩🇪', 'بنز، بی‌ام‌و، فولکس'),
];

class OriginCard extends StatelessWidget {
  const OriginCard({super.key, required this.origin, required this.onTap});
  final Origin origin;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: const BorderSide(color: Brand.line)),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: SizedBox(
          width: 112,
          child: Padding(
            padding: const EdgeInsets.all(10),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(origin.flag, style: const TextStyle(fontSize: 26)),
                const SizedBox(height: 4),
                Text(origin.label, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 13)),
                Text(
                  origin.makes,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 10, color: Brand.muted),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
