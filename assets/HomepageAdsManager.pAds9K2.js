import { J as C, aj as S, r as i, K as o, j as e, M as c, N as B, L as x, X as D, S as E, T as F, U as A, W as I, V as O } from "./index.H8SlvROM.js";
import { T as m } from "./textarea.BgqV-tzl.js";
import { T as M, a as H, b as y, c as n, d as L, e as l } from "./table.Bvn4wcNJ.js";
import { D as q, a as z, b as R, c as U, f as J } from "./dialog.Ca5uCzMR.js";
import { B as K } from "./BlueButton.Br8p7tO8.js";
import { S as P } from "./save.C7tPyNsR.js";
import { S as V } from "./square-pen.SuJRvEro.js";
import { T as W } from "./trash-2.sTRN-RWX.js";
import { M as fe } from "./MediaLibraryDialog.DqBdwoNk.js";
function ae() {
  const { toast: r } = C();
  S();
  const [h, w] = i.useState([]),
    [N, f] = i.useState(!1),
    [b, _] = i.useState(!1),
    [d, u] = i.useState(null),
    [t, a] = i.useState({ media_url: "", media_type: "image", link_url: "", display_order: 0, is_active: !0 }),
    [pe, me] = i.useState(!1);
  i.useEffect(() => {
    g();
  }, []);
  const g = async () => {
      try {
        f(!0);
        const s = await o.getAllSiteSettings();
        w(s.homepage_ads || []);
      } catch (s) {
        r({ title: "Error", description: s.message || "Failed to load homepage ads", variant: "destructive" });
      } finally {
        f(!1);
      }
    },
    v = (s) => {
      s
        ? (u(s),
          a({
            media_url: s.media_url || "",
            media_type: s.media_type || "image",
            link_url: s.link_url || "",
            display_order: s.display_order || 0,
            is_active: s.is_active !== !1,
          }))
        : (u(null), a({ media_url: "", media_type: "image", link_url: "", display_order: 0, is_active: !0 })),
        _(!0);
    },
    j = () => {
      _(!1), u(null), a({ media_url: "", media_type: "image", link_url: "", display_order: 0, is_active: !0 });
    },
    k = async (s) => {
      s.preventDefault();
      try {
        d ? (await o.updateHomepageAd(d.id, t), r({ title: "Success", description: "Homepage ad updated successfully" })) : (await o.createHomepageAd(t), r({ title: "Success", description: "Homepage ad created successfully" })), j(), g();
      } catch (p) {
        r({ title: "Error", description: p.message || "Failed to save ad", variant: "destructive" });
      }
    },
    T = async (s) => {
      if (confirm("Delete this homepage ad?")) {
        try {
          await o.deleteHomepageAd(s), r({ title: "Success", description: "Deleted successfully" }), g();
        } catch (p) {
          r({ title: "Error", description: p.message || "Failed to delete", variant: "destructive" });
        }
      }
    };
  return N && h.length === 0
    ? e.jsx("div", { className: "text-center py-12", children: "Loading..." })
    : e.jsxs("div", {
        className: "space-y-6",
        children: [
          e.jsxs("div", {
            className: "flex items-center justify-between",
            children: [
              e.jsxs("div", {
                children: [
                  e.jsx("h2", { className: "text-2xl font-bold", children: "Homepage ads" }),
                  e.jsx("p", {
                    className: "text-muted-foreground",
                    children: "Images, GIFs, or videos in the main advertising area (carousel when several are active).",
                  }),
                ],
              }),
              e.jsx(K, { onClick: () => v(), children: "Add ad" }),
              e.jsx(q, {
                open: b,
                onOpenChange: (s) => {
                  s || j();
                },
                children: e.jsxs(z, {
                  className: "max-w-2xl max-h-[90vh] overflow-y-auto",
                  children: [
                    e.jsxs(R, {
                      children: [
                        e.jsx(U, { children: d ? "Edit homepage ad" : "Add homepage ad" }),
                        e.jsx(J, {
                          children: "Set media URL (or pick from library), type, optional click-through link, and display order.",
                        }),
                      ],
                    }),
                    e.jsxs("form", {
                      onSubmit: k,
                      className: "space-y-4",
                      children: [
                        e.jsxs("div", {
                          className: "space-y-2",
                          children: [
                            e.jsx(c, { htmlFor: "media_type", children: "Media type *" }),
                            e.jsxs("select", {
                              id: "media_type",
                              className: "w-full border rounded-md p-2 bg-background",
                              value: t.media_type,
                              onChange: (s) => a({ ...t, media_type: s.target.value }),
                              children: [
                                e.jsx("option", { value: "image", children: "Image" }),
                                e.jsx("option", { value: "gif", children: "GIF" }),
                                e.jsx("option", { value: "video", children: "Video" }),
                              ],
                            }),
                          ],
                        }),
                        e.jsxs("div", {
                          className: "space-y-2",
                          children: [
                            e.jsx(c, { htmlFor: "media_url", children: "Media URL *" }),
                            e.jsxs("div", {
                              className: "flex gap-2",
                              children: [
                                e.jsx(m, { id: "media_url", value: t.media_url, onChange: (s) => a({ ...t, media_url: s.target.value }), required: !0, rows: 2 }),
                                e.jsx(x, { type: "button", variant: "outline", onClick: () => me(!0), children: "Media library" }),
                              ],
                            }),
                          ],
                        }),
                        e.jsxs("div", {
                          className: "space-y-2",
                          children: [
                            e.jsx(c, { htmlFor: "link_url", children: "Click-through URL (optional)" }),
                            e.jsx(B, { id: "link_url", type: "url", value: t.link_url, onChange: (s) => a({ ...t, link_url: s.target.value }), placeholder: "https://" }),
                          ],
                        }),
                        e.jsxs("div", {
                          className: "space-y-2",
                          children: [
                            e.jsx(c, { htmlFor: "display_order", children: "Display order" }),
                            e.jsx(B, {
                              id: "display_order",
                              type: "number",
                              value: t.display_order,
                              onChange: (s) => a({ ...t, display_order: parseInt(s.target.value, 10) || 0 }),
                            }),
                          ],
                        }),
                        e.jsxs("div", {
                          className: "flex items-center space-x-2",
                          children: [
                            e.jsx("input", {
                              type: "checkbox",
                              id: "is_active",
                              checked: t.is_active,
                              onChange: (s) => a({ ...t, is_active: s.target.checked }),
                              className: "rounded",
                            }),
                            e.jsx(c, { htmlFor: "is_active", children: "Active" }),
                          ],
                        }),
                        e.jsxs("div", {
                          className: "flex justify-end gap-2",
                          children: [
                            e.jsxs(x, { type: "button", variant: "outline", onClick: j, children: [e.jsx(D, { className: "w-4 h-4 mr-2" }), "Cancel"] }),
                            e.jsxs(x, { type: "submit", children: [e.jsx(P, { className: "w-4 h-4 mr-2" }), d ? "Update" : "Create"] }),
                          ],
                        }),
                      ],
                    }),
                  ],
                }),
              }),
            ],
          }),
          e.jsx(fe, {
            open: pe,
            onOpenChange: me,
            onSelect: (s) => {
              a({ ...t, media_url: s }), me(!1);
            },
          }),
          e.jsxs(E, {
            children: [
              e.jsxs(F, { children: [e.jsx(A, { children: "Homepage ads" }), e.jsx(I, { children: "Lower numbers appear first in the carousel." })] }),
              e.jsx(O, {
                children:
                  h.length === 0
                    ? e.jsx("div", { className: "text-center py-8 text-muted-foreground", children: "No ads yet — the site shows the default placeholder." })
                    : e.jsxs(M, {
                        children: [
                          e.jsx(H, {
                            children: e.jsxs(y, {
                              children: [
                                e.jsx(n, { children: "Order" }),
                                e.jsx(n, { children: "Type" }),
                                e.jsx(n, { children: "Media URL" }),
                                e.jsx(n, { children: "Link" }),
                                e.jsx(n, { children: "Status" }),
                                e.jsx(n, { children: "Actions" }),
                              ],
                            }),
                          }),
                          e.jsx(L, {
                            children: h.map((s) =>
                              e.jsxs(
                                y,
                                {
                                  children: [
                                    e.jsx(l, { children: s.display_order }),
                                    e.jsx(l, { children: s.media_type }),
                                    e.jsx(l, { className: "max-w-xs truncate font-mono text-xs", children: s.media_url }),
                                    e.jsx(l, { className: "max-w-[140px] truncate text-xs", children: s.link_url || "—" }),
                                    e.jsx(l, {
                                      children: e.jsx("span", {
                                        className: `px-2 py-1 rounded text-xs ${s.is_active ? "bg-green-100 text-green-800" : "bg-gray-100 text-gray-800"}`,
                                        children: s.is_active ? "Active" : "Inactive",
                                      }),
                                    }),
                                    e.jsx(l, {
                                      children: e.jsxs("div", {
                                        className: "flex gap-2",
                                        children: [
                                          e.jsx(x, { variant: "ghost", size: "sm", onClick: () => v(s), children: e.jsx(V, { className: "w-4 h-4" }) }),
                                          e.jsx(x, { variant: "ghost", size: "sm", onClick: () => T(s.id), children: e.jsx(W, { className: "w-4 h-4 text-destructive" }) }),
                                        ],
                                      }),
                                    }),
                                  ],
                                },
                                s.id
                              )
                            ),
                          }),
                        ],
                      }),
              }),
            ],
          }),
        ],
      });
}
export { ae as default };
