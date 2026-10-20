/* LwLive 落地页中转 Worker
   - 挂在 img.yjtv.top/latest/* 路由
   - 落地页只请求 img.yjtv.top，后台地址 api.yjtv.top 写死在此文件中，前台不可见
   - /latest/ios  → 返回 { url, ts, version }（ts 为文件真实上传时间戳）
   - /latest/android → 同上
   - 数据源：后台 config.json（iOS/安卓 downloadUrl + 最新版本）
*/
const UPSTREAM = "https://api.yjtv.top/data/config.json";

const cors = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Methods": "GET, HEAD, OPTIONS",
  "Access-Control-Allow-Headers": "*",
  "Cache-Control": "no-store"
};

export default {
  async fetch(request) {
    const url = new URL(request.url);

    if (request.method === "OPTIONS") {
      return new Response(null, { status: 204, headers: cors });
    }

    const plat = url.pathname === "/latest/ios" ? "ios"
               : url.pathname === "/latest/android" ? "android" : null;
    if (!plat) {
      return new Response("not found", { status: 404, headers: cors });
    }

    try {
      const cfgResp = await fetch(UPSTREAM);
      let cfgTs = 0;
      const cfgLm = cfgResp.headers.get("last-modified");
      if (cfgLm) { const t = Date.parse(cfgLm); if (!isNaN(t)) cfgTs = t; }
      const cfg = await cfgResp.json();

      const dl = (cfg[plat] && cfg[plat].downloadUrl) || "";

      // 读下载文件响应头拿真实上传时间（服务端无跨域限制，安卓 etag 也能读）
      let ts = 0;
      if (dl) {
        try {
          const h = await fetch(dl, { method: "HEAD", redirect: "follow" });
          const lm = h.headers.get("last-modified");
          if (lm) { const t = Date.parse(lm); if (!isNaN(t)) ts = t; }
          if (!ts) {
            const et = h.headers.get("etag");
            if (et) { const m = et.match(/(\d{13})/); if (m) ts = parseInt(m[1], 10); }
          }
        } catch (e) {}
      }
      if (!ts) ts = cfgTs; // 兜底：后台配置保存时间

      const version = (cfg[plat] && cfg[plat].latestVersion) || "";
      const content = (cfg[plat] && cfg[plat].updateContent) || "";
      const changelog = (cfg[plat] && cfg[plat].changelog) || [];
      const logs = changelog.length ? changelog : [{ version: version, ts: ts, content: content }];
      const body = JSON.stringify({ url: dl, ts: ts, version: version, content: content, logs: logs });
      return new Response(body, {
        headers: { ...cors, "Content-Type": "application/json; charset=utf-8" }
      });
    } catch (e) {
      return new Response(JSON.stringify({ url: "", ts: 0, version: "", content: "", logs: [] }), {
        status: 502,
        headers: { ...cors, "Content-Type": "application/json; charset=utf-8" }
      });
    }
  }
};
