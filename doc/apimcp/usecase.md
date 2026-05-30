# MCP API — Use Cases (Mô tả tính năng bằng lời)

> Tài liệu này mô tả các tình huống thực tế Claude MCP sẽ làm gì trên KNXStore.vn.
> Không có code — chỉ có hành động và kết quả.

---

## UC-01: Claude kiểm tra site cần làm gì trước khi bắt đầu

**Tình huống:** Tùng mở Claude và nói "hôm nay mình cần fill content cho catalog KNX".

**Claude làm gì:**
1. Gọi audit endpoint — nhận về danh sách tất cả entity đang thiếu content
2. Báo cáo: "Site có 87 mục chưa đầy đủ: 42 sản phẩm thiếu mô tả tiếng Anh, 15 bài blog chưa có SEO, 8 brand chưa có description"
3. Hỏi Tùng muốn bắt đầu từ đâu — sản phẩm, blog, hay SEO trước

**Kết quả:** Tùng biết toàn bộ bức tranh, Claude có kế hoạch rõ ràng, không làm lung tung.

---

## UC-02: Tạo sản phẩm mới từ datasheet kỹ thuật

**Tình huống:** Tùng có file spec của sản phẩm JUNG 2094 TSM — nút nhấn KNX 4 kênh. Muốn Claude tạo nội dung cho sản phẩm này.

**Claude làm gì:**
1. Nhận text từ datasheet (hoặc Tùng paste vào)
2. Tự động phân tích: đây là sản phẩm của JUNG, thuộc nhóm KNX Input Devices, có 4 kênh, giao thức KNX TP, nguồn bus
3. **Kiểm tra trước:** tìm xem `manufacturer:jung` và `category:knx-input-devices` đã có trong DB chưa
4. Nếu chưa có → tạo stub placeholder cho manufacturer và category (trạng thái chưa hiển thị)
5. Tạo sản phẩm ở trạng thái draft với: mô tả tiếng Việt, mô tả tiếng Anh, SEO meta cả 2 ngôn ngữ, bảng thông số kỹ thuật, 5 câu FAQ
6. Báo cáo: "Đã tạo sản phẩm draft. Cũng tạo stub cho JUNG và KNX Input Devices — cần fill thêm nội dung cho 2 mục này"

**Kết quả:** 1 cuộc hội thoại với Claude → sản phẩm đầy đủ ở trạng thái draft, kèm danh sách việc còn lại.

---

## UC-03: Import hàng loạt 50 sản phẩm từ catalog PDF của một hãng

**Tình huống:** KNXStore nhập hàng mới từ ABB — có catalog PDF với 50 sản phẩm. Tùng muốn đưa hết vào site.

**Claude làm gì:**
1. Đọc catalog → lập danh sách 50 sản phẩm
2. Tạo manufacturer ABB một lần (nếu chưa có)
3. Tạo các category cần thiết (nếu chưa có)
4. Lần lượt tạo 50 sản phẩm draft, mỗi cái có đầy đủ mô tả + thông số + SEO
5. Với sản phẩm thứ 2 trở đi: ABB và category đã tồn tại → tái sử dụng, không tạo trùng
6. Cuối cùng: "Đã tạo 50 sản phẩm draft. Cần Tùng review và kích hoạt từng cái"

**Kết quả:** Catalog 50 sản phẩm được đưa vào hệ thống trong vài phút thay vì vài ngày nhập tay.

---

## UC-04: Dịch nội dung tiếng Việt sang tiếng Anh hàng loạt

**Tình huống:** Site có 200 sản phẩm đã có mô tả tiếng Việt đầy đủ nhưng chưa có tiếng Anh. Tùng muốn dịch hàng loạt.

**Claude làm gì:**
1. Audit → lọc ra danh sách sản phẩm có mô tả vi nhưng thiếu en
2. Dịch theo từng batch 20 sản phẩm: description, short_description, meta_title, meta_description
3. Mặc định không ghi đè nếu tiếng Anh đã có (bảo vệ nội dung cũ)
4. Báo cáo từng batch: đã dịch 20, bỏ qua 3 (đã có en), lỗi 0
5. Sau khi hoàn tất: "Đã dịch 197 sản phẩm. 3 sản phẩm đã có tiếng Anh — không thay đổi"

**Kết quả:** Toàn bộ catalog có nội dung song ngữ vi/en mà không cần dịch từng cái.

---

## UC-05: Fill SEO cho toàn bộ site trong một lần

**Tình huống:** Site đang thiếu meta_title và meta_description cho hầu hết entity. Google không crawl được đúng.

**Claude làm gì:**
1. Audit → lọc tất cả entity thiếu SEO meta (product, category, blog, brand, manufacturer)
2. Với mỗi entity: đọc tên + mô tả hiện có → tạo meta_title (max 70 ký tự) và meta_description (max 155 ký tự) phù hợp
3. Ghi vào DB cho cả vi và en, không ghi đè nếu đã có
4. Báo cáo: "Đã fill SEO cho 87 mục: 42 sản phẩm, 8 danh mục, 15 bài blog, 10 brand, 12 nhà sản xuất"

**Kết quả:** Toàn bộ site có SEO meta đầy đủ — Google có thể đọc được tất cả.

---

## UC-06: Viết bài blog kỹ thuật về KNX

**Tình huống:** Tùng muốn có bài viết "KNX là gì? Hướng dẫn tổng quan cho System Integrator".

**Claude làm gì:**
1. Tùng mô tả chủ đề và audience (System Integrator, B2B)
2. Claude tìm kiếm: danh mục blog nào phù hợp? sản phẩm KNX nào đang có trên site để link vào?
3. Viết bài hoàn chỉnh: tiêu đề, giới thiệu, nội dung kỹ thuật, FAQ 5 câu, link đến sản phẩm liên quan
4. Tạo cả bản tiếng Anh "What is KNX? Overview for System Integrators"
5. Fill SEO meta cho cả 2 ngôn ngữ
6. Save ở trạng thái **draft** — không publish tự động
7. Báo: "Bài đã sẵn sàng để Tùng review tại /admin/blog/knx-la-gi/edit"

**Kết quả:** Bài blog đầy đủ, song ngữ, có SEO, có FAQ, chờ review — Tùng chỉ cần đọc và nhấn Publish.

---

## UC-07: Tùng review và kích hoạt nội dung MCP đã tạo

**Tình huống:** Claude đã làm việc 1 tiếng, tạo nhiều draft. Tùng muốn biết cần review gì.

**Claude làm gì:**
1. Gọi review queue → trả về danh sách draft MCP tạo ra chưa được kích hoạt
2. Với mỗi item: hiển thị readiness score, vấn đề còn lại (nếu có), link vào Filament để edit
3. Tùng nói "kích hoạt sản phẩm KNX Push Button" → Claude chạy readiness check trước
4. Nếu readiness pass → kích hoạt → Observer tự tạo JSON-LD, sitemap, LLMs entry
5. Nếu readiness fail → báo lý do cụ thể: "category 'knx-input-devices' chưa active, cần kích hoạt category trước"

**Kết quả:** Không có gì lên site mà chưa qua tay người — Claude chỉ làm draft, Tùng quyết định publish.

---

## UC-08: Cập nhật mô tả sản phẩm đã có sẵn

**Tình huống:** Nhà cung cấp cập nhật spec của sản phẩm JUNG 2094 TSM. Tùng muốn Claude viết lại mô tả.

**Claude làm gì:**
1. Lấy context của sản phẩm: nội dung hiện tại + SEO + FAQ
2. Tùng cung cấp spec mới
3. Claude viết lại description và short_description — chỉ những field được chỉ định
4. Vì `overwrite_existing` mặc định là `false`: nếu SEO đã tốt → giữ nguyên, không ghi đè
5. Cập nhật FAQ nếu có thông tin mới liên quan
6. Save lại, sản phẩm vẫn ở trạng thái active — Observer tự cập nhật JSON-LD

**Kết quả:** Mô tả được cập nhật, SEO giữ nguyên nếu đã tốt, không làm hỏng gì đang hoạt động.

---

## UC-09: Claude tìm sản phẩm để link trong bài blog

**Tình huống:** Claude đang viết bài về "So sánh KNX và DALI-2" — cần link đến sản phẩm thực tế trên site.

**Claude làm gì:**
1. Tìm kiếm: `search?q=KNX input device&types=product`
2. Nhận về danh sách sản phẩm KNX đang active trên site
3. Tìm tiếp: `search?q=DALI-2 controller&types=product`
4. Nhúng link sản phẩm thực tế vào nội dung bài blog thay vì để placeholder

**Kết quả:** Bài blog có internal link thực tế → tốt cho SEO và trải nghiệm người đọc. Claude không bịa link.

---

## UC-10: Bảo vệ nội dung Tùng tự viết

**Tình huống:** Tùng tự viết tay description cho sản phẩm flagship — không muốn Claude ghi đè khi chạy bulk translate sau này.

**Tùng làm gì:**
1. Vào Filament admin → tìm sản phẩm
2. Toggle `is_mcp_protected = true` trên translation đó

**Kết quả:** Sau này khi Claude chạy bulk translate hay batch SEO, sản phẩm này bị bỏ qua hoàn toàn. Nội dung Tùng viết được bảo toàn vĩnh viễn cho đến khi Tùng tự tắt flag.

---

## Tổng kết luồng làm việc thông thường

```
Tùng → "Claude, hôm nay làm gì?"
Claude → audit() → "Site thiếu 87 mục. Làm sản phẩm JUNG trước nhé?"
Tùng → "Oke, import catalog này đi"

Claude → import_from_specs() × 50 lần
       → auto-create stubs cho manufacturer, category
       → tạo 50 sản phẩm draft đầy đủ

Claude → "Xong. Vào review queue để xem nhé"

Tùng → xem review queue → chọn sản phẩm muốn activate
Claude → readiness_check() → "Sản phẩm đủ điều kiện. Kích hoạt?"
Tùng → "Oke"
Claude → activate() → Observer → JSON-LD + Sitemap + LLMs tự tạo

Kết quả: Sản phẩm lên site, đầy đủ SEO, không cần nhập tay.
```
