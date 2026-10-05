import { QRCodeSVG } from "qrcode.react";
import { JOIN_URL } from "../config";

export function QrPanel() {
  return (
    <div className="qr">
      <div className="qr__code">
        <QRCodeSVG value={JOIN_URL} size={260} level="M" bgColor="#ffffff" fgColor="#0a0d14" marginSize={2} />
      </div>
      <div className="qr__copy">
        <div className="qr__title">SCAN TO PLAY</div>
        <div className="qr__text">Join the next round in Telegram.<br />$10,000 virtual · up to 100× · win the pool</div>
        <div className="qr__url">{JOIN_URL.replace(/^https?:\/\//, "")}</div>
      </div>
    </div>
  );
}
