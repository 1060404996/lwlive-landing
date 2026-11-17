/* LwLive 落地页中转 Worker（v2 · MySQL 新后台）
   - 挂在 img.yjtv.top/latest/* 路由
   - 数据源：新后台 api.9933333.xyz/config.php（配置存在 MySQL 里），后台地址写死在此文件，前台不可见
   - /latest/ios       → { url, ts, version, content, logs }
   - /latest/android   → 同上
   - /latest/changelog → { logs:[{version, ts, content}] }
   新接口返回形如：{ code:0, data:{ ios:{downloadUrl,latestVersion,updateContent}, android:{...} } }
   旧版为扁平结构（ios/android 直接在顶层），此处两者都兼容。
*/
const UPSTREAM = "https://api.9933333.xyz/config.php";

const cors = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Methods": "GET, HEAD, OPTIONS",
  "Access-Control-Allow-Headers": "*",
  "Cache-Control": "no-store"
};

function jsonResp(obj, status) {
  return new Response(JSON.stringify(obj), {
    status: status || 200,
    headers: { ...cors, "Content-Type": "application/json; charset=utf-8" }
  });
}

/* 从配置里取出某个平台的信息：兼容 {data:{ios:{...}}} 与 {ios:{...}} */
function pickPlatform(root, plat) {
  if (!root) return { url: "", version: "", content: "" };
  const obj = root[plat] || {};
  return {
    url: obj.downloadUrl || "",
    version: obj.latestVersion || "",
    content: obj.updateContent || ""
  };
}

/* 用响应头拿文件真实上传时间；失败则回落到配置本身的时间 */
async function fileTime(url) {
  if (!url) return 0;
  try {
    const h = await fetch(url, { method: "HEAD", redirect: "follow" });
    const lm = h.headers.get("last-modified");
    if (lm) { const t = Date.parse(lm); if (!isNaN(t)) return t; }
    const et = h.headers.get("etag");
    if (et) {
      const m = et.match(/(\d{13})/);
      if (m) return parseInt(m[1], 10);
    }
  } catch (e) {}
  return 0;
}

export default {
  async fetch(request) {
    const url = new URL(request.url);

    if (request.method === "OPTIONS") {
      return new Response(null, { status: 204, headers: cors });
    }

    const plat = url.pathname === "/latest/ios" ? "ios"
               : url.pathname === "/latest/android" ? "android" : null;
    const isLog = url.pathname === "/latest/changelog";

    if (!plat && !isLog) {
      return new Response("not found", { status: 404, headers: cors });
    }

    try {
      const r = await fetch(UPSTREAM, { headers: { "Accept": "application/json" } });
      let cfgTs = 0;
      const lm = r.headers.get("last-modified");
      if (lm) { const t = Date.parse(lm); if (!isNaN(t)) cfgTs = t; }

      const raw = await r.json();
      const root = (raw && raw.data) ? raw.data : raw;

      /* 更新日志：新后台没有独立日志接口，用 iOS 更新公告作为一条记录 */
      if (isLog) {
        const info = pickPlatform(root, "ios");
        const logs = [];
        if (info.content || info.version) {
          logs.push({ version: info.version, ts: cfgTs || 0, content: info.content });
        }
        return jsonResp({ logs: logs });
      }

      const info = pickPlatform(root, plat);
      let ts = await fileTime(info.url);
      if (!ts) ts = cfgTs;

      return jsonResp({
        url: info.url,
        ts: ts,
        version: info.version,
        content: info.content,
        logs: info.content ? [{ version: info.version, ts: ts, content: info.content }] : []
      });
    } catch (e) {
      return jsonResp({ url: "", ts: 0, version: "", content: "", logs: [] }, 502);
    }
  }
};
