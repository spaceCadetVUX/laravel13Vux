Tổng quan từ codebase
Tình trạng hiện tại:

Resources đều dùng whenLoaded() — pattern đúng
Product & Blog services có eager loading đầy đủ
OrderController dùng Eloquent trực tiếp (không qua service) — rủi ro cao nhất
preventLazyLoading() chưa được thêm
Rủi ro cao nhất (dự đoán):

#	Chỗ nguy hiểm	Lý do
1	OrderController::index/show	Load items nhưng chưa load items.product — nếu OrderItemResource access ->product thì N+1
2	CartController	Cần check CartItemResource có access ->product không
3	BlogCommentResource	Check author có được eager load không
4	CategoryTreeResource	Đệ quy children có thể gây N+1
5	Traits HasSeoMeta, HasJsonldSchemas	Nếu access relationship bên trong trait thì service phải eager load
Phase 1 — Thêm Safety Net (15 phút)
Thêm preventLazyLoading() vào AppServiceProvider::boot():


Model::preventLazyLoading(! app()->isProduction());
Bất kỳ lazy load nào sẽ throw LazyLoadingViolationException trong local/test.

Phase 2 — Chạy Test Suite (detect tự động)

cd backend
php artisan test
Mọi N+1 trong test cases sẽ crash ngay — danh sách lỗi chính là todo list để fix.

Phase 3 — Fix từng N+1 theo thứ tự ưu tiên
3a. Order (rủi ro cao nhất):

Check OrderItemResource — có access $this->product không?
Nếu có → OrderController::index phải đổi thành Order::with('items.product')
3b. Cart:

Check CartItemResource — có access ->product không?
Nếu có → CartController phải load with('items.product')
3c. Blog Comments:

Check BlogCommentResource — có access ->author không?
BlogPostController::show có load comments.author không?
3d. Category Tree:

CategoryTreeResource có gọi ->children đệ quy không?
Nếu có → cần with('children.children') hoặc flatten với loadMissing
3e. SEO Traits:

HasSeoMeta, HasJsonldSchemas — trace xem có access relationship không
Phase 4 — Manual QueryLog cho endpoints chưa có test
Với endpoints không có feature test, dùng DB::enableQueryLog() tạm thời trong controller để đếm queries:


DB::enableQueryLog();
// ... logic
$queries = DB::getQueryLog(); // count > expected → N+1
Phase 5 — Verify & Cleanup

php artisan test  # phải pass 100%
Xóa bất kỳ DB::enableQueryLog() debug code nào đã thêm.

Thứ tự thực hiện

Phase 1 → Phase 2 → Phase 3 (a→b→c→d→e) → Phase 4 → Phase 5