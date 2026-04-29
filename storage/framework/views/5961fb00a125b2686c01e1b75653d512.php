<?php $__env->startSection('title', isset($event) ? 'แก้ไขอีเวนต์' : 'สร้างอีเวนต์'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0">
    <i class="bi bi-<?php echo e(isset($event) ? 'pencil' : 'plus-lg'); ?> mr-2 text-indigo-500"></i>
    <?php echo e(isset($event) ? 'แก้ไขอีเวนต์' : 'สร้างอีเวนต์'); ?>

  </h4>
  <a href="<?php echo e(route('admin.events.index')); ?>" class="border border-gray-300 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 px-4 py-2 rounded-lg transition inline-flex items-center gap-1">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>


<?php if($errors->any()): ?>
<div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-4 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400">
  <div class="flex items-center gap-2 mb-1">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span class="font-semibold text-sm">พบข้อผิดพลาด กรุณาตรวจสอบข้อมูล</span>
  </div>
  <ul class="mb-0 text-sm list-disc list-inside">
    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <li><?php echo e($error); ?></li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </ul>
</div>
<?php endif; ?>

<form method="POST"
      action="<?php echo e(isset($event) ? route('admin.events.update', $event->id) : route('admin.events.store')); ?>"
      enctype="multipart/form-data">
  <?php echo csrf_field(); ?>
  <?php if(isset($event)): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    
    <div class="lg:col-span-2 space-y-4">

      
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/[0.06]">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="mb-0 font-semibold text-sm">
            <i class="bi bi-calendar-event mr-1.5 text-indigo-500"></i>ข้อมูลอีเวนต์
          </h6>
        </div>
        <div class="p-5 space-y-4">
          
          <div>
            <label for="title" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
              ชื่ออีเวนต์ <span class="text-red-500">*</span>
            </label>
            <input type="text" name="title" id="title"
                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-400 ring-1 ring-red-400 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                   value="<?php echo e(old('title', $event->name ?? '')); ?>"
                   placeholder="ระบุชื่ออีเวนต์"
                   required>
            <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>

          
          <div>
            <label for="description" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
              รายละเอียด
            </label>
            <textarea name="description" id="description" rows="4"
                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition <?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-400 ring-1 ring-red-400 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                      placeholder="อธิบายรายละเอียดอีเวนต์"><?php echo e(old('description', $event->description ?? '')); ?></textarea>
            <?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>

          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="category_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                หมวดหมู่ <span class="text-red-500">*</span>
              </label>
              <select name="category_id" id="category_id"
                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition <?php $__errorArgs = ['category_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-400 ring-1 ring-red-400 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                      required>
                <option value="">-- เลือกหมวดหมู่ --</option>
                <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($cat->id); ?>" <?php echo e(old('category_id', $event->category_id ?? '') == $cat->id ? 'selected' : ''); ?>>
                  <?php echo e($cat->name); ?>

                </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </select>
              <?php $__errorArgs = ['category_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
              <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <div>
              <label for="event_date" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                วันที่ถ่ายภาพ <span class="text-red-500">*</span>
              </label>
              <input type="date" name="event_date" id="event_date"
                     class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition <?php $__errorArgs = ['event_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-400 ring-1 ring-red-400 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                     value="<?php echo e(old('event_date', isset($event) ? $event->shoot_date?->format('Y-m-d') : '')); ?>"
                     required>
              <?php $__errorArgs = ['event_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
              <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
          </div>
        </div>
      </div>

      
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/[0.06]"
           x-data="{
              provinceId: <?php echo e(old('province_id', $event->province_id ?? 'null')); ?>,
              districtId: <?php echo e(old('district_id', $event->district_id ?? 'null')); ?>,
              subdistrictId: <?php echo e(old('subdistrict_id', $event->subdistrict_id ?? 'null')); ?>,
              districts: <?php echo e(isset($districts) ? $districts->toJson() : '[]'); ?>,
              subdistricts: <?php echo e(isset($subdistricts) ? $subdistricts->toJson() : '[]'); ?>,
              async fetchDistricts() {
                  if (!this.provinceId) { this.districts = []; this.subdistricts = []; this.districtId = null; this.subdistrictId = null; return; }
                  const res = await fetch('<?php echo e(route("admin.api.locations.districts")); ?>?province_id=' + this.provinceId);
                  this.districts = await res.json();
                  this.districtId = null; this.subdistrictId = null; this.subdistricts = [];
              },
              async fetchSubdistricts() {
                  if (!this.districtId) { this.subdistricts = []; this.subdistrictId = null; return; }
                  const res = await fetch('<?php echo e(route("admin.api.locations.subdistricts")); ?>?district_id=' + this.districtId);
                  this.subdistricts = await res.json();
                  this.subdistrictId = null;
              }
           }">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="mb-0 font-semibold text-sm">
            <i class="bi bi-geo-alt mr-1.5 text-indigo-500"></i>สถานที่
          </h6>
        </div>
        <div class="p-5 space-y-4">
          
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            
            <div>
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">จังหวัด</label>
              <select x-model="provinceId" @change="fetchDistricts()"
                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <option value="">-- เลือกจังหวัด --</option>
                <?php $__currentLoopData = $provinces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $province): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($province->id); ?>"><?php echo e($province->name_th); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </select>
              <?php $__errorArgs = ['province_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
              <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            
            <div>
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">อำเภอ/เขต</label>
              <select x-model="districtId" @change="fetchSubdistricts()"
                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                      :disabled="!provinceId">
                <option value="">-- เลือกอำเภอ/เขต --</option>
                <template x-for="d in districts" :key="d.id">
                  <option :value="d.id" x-text="d.name_th"></option>
                </template>
              </select>
              <?php $__errorArgs = ['district_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
              <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            
            <div>
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">ตำบล/แขวง</label>
              <select x-model="subdistrictId"
                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                      :disabled="!districtId">
                <option value="">-- เลือกตำบล/แขวง --</option>
                <template x-for="s in subdistricts" :key="s.id">
                  <option :value="s.id" x-text="s.name_th + (s.zip_code ? ' (' + s.zip_code + ')' : '')"></option>
                </template>
              </select>
              <?php $__errorArgs = ['subdistrict_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
              <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
          </div>

          
          <input type="hidden" name="province_id" :value="provinceId">
          <input type="hidden" name="district_id" :value="districtId">
          <input type="hidden" name="subdistrict_id" :value="subdistrictId">

          
          <div>
            <label for="location_detail" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
              รายละเอียดสถานที่เพิ่มเติม
            </label>
            <input type="text" name="location_detail" id="location_detail"
                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                   value="<?php echo e(old('location_detail', $event->location_detail ?? '')); ?>"
                   placeholder="เช่น ชื่อสถานที่, ซอย, ถนน">
            <?php $__errorArgs = ['location_detail'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
        </div>
      </div>

      
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/[0.06]">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="mb-0 font-semibold text-sm">
            <i class="bi bi-google mr-1.5 text-indigo-500"></i>Google Drive Photos
          </h6>
        </div>
        <div class="p-5 space-y-3">
          <div>
            <label for="drive_folder_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              Google Drive Folder URL or ID
            </label>
            <input type="text" name="drive_folder_url" id="drive_folder_url"
                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                   value="<?php echo e(old('drive_folder_url', $event->drive_folder_id ?? '')); ?>"
                   placeholder="https://drive.google.com/drive/folders/...">
            <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Paste the Google Drive shared folder link</p>
          </div>
          <?php if(isset($event) && $event->drive_folder_id): ?>
          <div class="bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 text-blue-700 dark:text-blue-400 rounded-lg p-3 text-sm">
            <i class="bi bi-info-circle mr-1"></i> Current folder ID: <span class="font-mono"><?php echo e($event->drive_folder_id); ?></span>
            <br>Photos cached: <?php echo e($event->photosCache->count() ?? 0); ?>

          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    
    <div class="lg:col-span-1">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/[0.06]">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="mb-0 font-semibold text-sm">
            <i class="bi bi-gear mr-1.5 text-indigo-500"></i>การตั้งค่า
          </h6>
        </div>
        <div class="p-5 space-y-4">

          
          <div>
            <label for="status" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">สถานะ</label>
            <select name="status" id="status"
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <?php $__currentLoopData = ['draft' => 'Draft', 'active' => 'Active', 'published' => 'Published', 'inactive' => 'Inactive', 'archived' => 'Archived']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($val); ?>" <?php echo e(old('status', $event->status ?? 'draft') === $val ? 'selected' : ''); ?>>
                <?php echo e($label); ?>

              </option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <?php $__errorArgs = ['status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>

          
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">ช่างภาพ</label>
            <?php if(isset($event) && $event->photographer_id): ?>
            <input type="text" class="w-full px-4 py-2.5 border border-gray-200 dark:border-gray-600 rounded-lg text-sm bg-gray-50 dark:bg-slate-700 dark:text-gray-300" value="<?php echo e($event->photographer->name ?? 'ID: ' . $event->photographer_id); ?>" readonly>
            <input type="hidden" name="photographer_id" value="<?php echo e($event->photographer_id); ?>">
            <?php else: ?>
            <div class="bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-gray-600 rounded-lg px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400">
              <i class="bi bi-person mr-1"></i> ระบุภายหลังได้
            </div>
            <?php endif; ?>
          </div>

          
          <div x-data="{ visibility: '<?php echo e(old('visibility', $event->visibility ?? 'public')); ?>' }">
            <label for="visibility" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">การมองเห็น</label>
            <select name="visibility" id="visibility" x-model="visibility"
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <option value="public">Public</option>
              <option value="private">Private</option>
              <option value="password">Password Protected</option>
            </select>
            <?php $__errorArgs = ['visibility'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

            
            <div x-show="visibility === 'password'" x-transition x-cloak class="mt-2">
              <label for="event_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                รหัสผ่านอีเวนต์
              </label>
              <input type="text" name="event_password" id="event_password"
                     class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                     value="<?php echo e(old('event_password', $event->event_password ?? '')); ?>"
                     placeholder="ระบุรหัสผ่าน">
              <?php $__errorArgs = ['event_password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
              <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
          </div>

          
          <div x-data="{ isFree: <?php echo e(old('is_free', $event->is_free ?? 0) ? 'true' : 'false'); ?> }">
            <label for="price_per_photo" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
              ราคาต่อรูป (THB)
            </label>
            <input type="number" name="price_per_photo" id="price_per_photo"
                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition disabled:opacity-50 disabled:cursor-not-allowed <?php $__errorArgs = ['price_per_photo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-400 ring-1 ring-red-400 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                   value="<?php echo e(old('price_per_photo', $event->price_per_photo ?? $minPrice)); ?>"
                   min="<?php echo e($minPrice); ?>"
                   step="0.01"
                   :disabled="isFree">
            <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">
              ราคาขั้นต่ำ: <span class="font-semibold"><?php echo e(number_format($minPrice, 2)); ?></span>
            </p>
            <?php $__errorArgs = ['price_per_photo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

            <?php if($minPrice > 0): ?>
            <div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-amber-700 dark:text-amber-400 rounded-lg p-2.5 text-xs mt-2">
              <i class="bi bi-info-circle mr-1"></i>
              ระบบกำหนดราคาขั้นต่ำไว้ที่ <?php echo e(number_format($minPrice, 2)); ?> บาท/รูป
            </div>
            <?php endif; ?>

            
            <?php if($allowFree ?? true): ?>
            <div class="flex items-center gap-2 mt-3">
              <input type="hidden" name="is_free" value="0">
              <input type="checkbox" name="is_free" id="is_free"
                     class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                     value="1"
                     x-model="isFree"
                     <?php echo e(old('is_free', $event->is_free ?? 0) ? 'checked' : ''); ?>>
              <label for="is_free" class="text-sm text-gray-700 dark:text-gray-300">ฟรี (ไม่คิดค่าบริการ)</label>
            </div>
            <?php else: ?>
            <div class="mt-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-600 dark:text-red-400 text-xs rounded-lg px-3 py-2">
              <i class="bi bi-lock mr-1"></i> ระบบปิดการใช้งาน "ฟรี" — อีเวนต์ต้องมีค่าบริการ
            </div>
            <?php endif; ?>
          </div>

          
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">ภาพหน้าปก</label>

            
            <div id="coverPreviewWrap" class="mb-2 text-center" style="<?php echo e((isset($event) && $event->cover_image) ? '' : 'display:none'); ?>">
              <img id="coverPreview"
                   src="<?php echo e((isset($event) && $event->cover_image) ? $event->cover_image_url : ''); ?>"
                   class="w-full h-auto rounded-lg shadow-sm border-2 border-gray-200 dark:border-gray-600"
                   style="max-height:180px; object-fit:cover;"
                   alt="Cover preview">
            </div>

            
            <label class="w-full relative cursor-pointer block">
              <div id="coverDropZone"
                   class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl text-center p-4 bg-gray-50 dark:bg-slate-700/50 relative transition hover:border-indigo-400 hover:bg-indigo-50/30 dark:hover:border-indigo-400 dark:hover:bg-indigo-500/5">
                <i class="bi bi-cloud-arrow-up text-3xl text-indigo-500 block mb-1"></i>
                <span class="text-gray-500 dark:text-gray-400 text-sm">คลิกหรือลากวางไฟล์รูปภาพ</span>
                <br><span class="text-gray-400 dark:text-gray-500 text-[0.7rem]">JPEG, PNG, WebP -- ไม่เกิน 10MB</span>
              </div>
              <input type="file" name="cover_image" id="coverInput"
                     class="absolute top-0 left-0 w-full h-full opacity-0 cursor-pointer"
                     accept="image/jpeg,image/png,image/webp,image/gif">
            </label>
            <?php $__errorArgs = ['cover_image'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>

          
          <div class="pt-4 mt-2 border-t border-gray-200 dark:border-white/10">
            <h6 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-2">
              <i class="bi bi-hourglass-split mr-1 text-red-500"></i> Auto-Delete Policy
            </h6>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
              ใช้เมื่อเปิด Retention Policy ในหน้า Settings — กำหนดต่อ Event ได้
            </p>

            
            <label class="flex items-start gap-2 mb-3 cursor-pointer p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5">
              <input type="hidden" name="auto_delete_exempt" value="0">
              <input type="checkbox" name="auto_delete_exempt" id="auto_delete_exempt" value="1"
                     <?php echo e(old('auto_delete_exempt', $event->auto_delete_exempt ?? 0) ? 'checked' : ''); ?>

                     class="mt-0.5 w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
              <span class="text-xs">
                <span class="font-medium text-gray-700 dark:text-gray-300">ห้ามลบอัตโนมัติ (pin)</span><br>
                <span class="text-gray-500">อีเวนต์นี้จะถูกข้ามเสมอ</span>
              </span>
            </label>

            
            <div class="mb-3">
              <label for="retention_days_override" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                จำนวนวันเก็บ (override)
              </label>
              <input type="number" name="retention_days_override" id="retention_days_override"
                     min="1" max="3650"
                     value="<?php echo e(old('retention_days_override', $event->retention_days_override ?? '')); ?>"
                     placeholder="ว่างไว้ = ใช้ค่า default"
                     class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100">
              <?php $__errorArgs = ['retention_days_override'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
              <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p>
              <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            
            <div>
              <label for="auto_delete_at" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                วันที่ลบเฉพาะ (override ทุกอย่าง)
              </label>
              <input type="date" name="auto_delete_at" id="auto_delete_at"
                     value="<?php echo e(old('auto_delete_at', optional($event->auto_delete_at ?? null)->format('Y-m-d'))); ?>"
                     class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100">
              <?php $__errorArgs = ['auto_delete_at'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
              <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p>
              <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
          </div>

          
          <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700/50 rounded-xl p-4 mb-4">
            <h3 class="text-sm font-semibold text-indigo-900 dark:text-indigo-200 mb-2 flex items-center gap-1.5">
              <i class="bi bi-person-bounding-box"></i>
              ค้นหาด้วยใบหน้า (PDPA §26)
            </h3>
            <p class="text-xs text-indigo-700 dark:text-indigo-300 mb-3">
              เปิดไว้จะให้ผู้เข้าร่วมงานอัปโหลด selfie ค้นหารูปตัวเองได้ (รูปใบหน้าเป็น biometric data — ระบบบันทึกความยินยอมทุกครั้ง)
            </p>

            <label class="flex items-start gap-2 cursor-pointer p-2 rounded-lg hover:bg-white/50 dark:hover:bg-white/5">
              <input type="hidden" name="face_search_enabled" value="0">
              <input type="checkbox" name="face_search_enabled" id="face_search_enabled" value="1"
                     <?php echo e(old('face_search_enabled', $event->face_search_enabled ?? 1) ? 'checked' : ''); ?>

                     class="mt-0.5 w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500">
              <span class="text-xs">
                <span class="font-medium text-gray-700 dark:text-gray-300">เปิดค้นหาด้วยใบหน้า</span><br>
                <span class="text-gray-500">ปิดเพื่อซ่อนปุ่ม "ค้นหาด้วยใบหน้า" และบล็อก API ของงานนี้ (เช่น งาน NDA, งานที่มีเยาวชน)</span>
              </span>
            </label>
            <?php $__errorArgs = ['face_search_enabled'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>

          
          <div class="pt-2">
            <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5 rounded-lg transition inline-flex items-center justify-center gap-1.5">
              <i class="bi bi-check-lg"></i>
              <?php echo e(isset($event) ? 'อัปเดต' : 'สร้าง'); ?> อีเวนต์
            </button>
          </div>

        </div>
      </div>
    </div>

  </div>
</form>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const input = document.getElementById('coverInput');
  const preview = document.getElementById('coverPreview');
  const wrap = document.getElementById('coverPreviewWrap');
  const dropZone = document.getElementById('coverDropZone');

  if (!input || !dropZone) return;

  // File selected -> show preview
  input.addEventListener('change', function() {
    handleFile(this.files);
  });

  function handleFile(files) {
    if (files && files[0]) {
      const file = files[0];

      // Validate file type
      const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
      if (!allowed.includes(file.type)) {
        if (typeof Swal !== 'undefined') {
          Swal.fire('ไฟล์ไม่รองรับ', 'กรุณาเลือกไฟล์ JPEG, PNG หรือ WebP', 'warning');
        }
        input.value = '';
        return;
      }

      // Validate size (10MB)
      if (file.size > 10 * 1024 * 1024) {
        if (typeof Swal !== 'undefined') {
          Swal.fire('ไฟล์ใหญ่เกินไป', 'กรุณาเลือกไฟล์ที่มีขนาดไม่เกิน 10MB', 'warning');
        }
        input.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onload = function(e) {
        preview.src = e.target.result;
        wrap.style.display = '';
        dropZone.innerHTML =
          '<i class="bi bi-check-circle text-2xl text-emerald-500 block mb-1"></i>' +
          '<span class="text-emerald-600 text-sm font-semibold">' + file.name + '</span>' +
          '<br><span class="text-gray-400 text-[0.7rem]">' + (file.size / 1024 / 1024).toFixed(2) + ' MB -- คลิกเพื่อเปลี่ยน</span>';
      };
      reader.readAsDataURL(file);
    }
  }

  // Drag & drop
  ['dragenter', 'dragover'].forEach(function(evt) {
    dropZone.addEventListener(evt, function(e) {
      e.preventDefault();
      e.stopPropagation();
      this.style.borderColor = '#6366f1';
      this.style.background = 'rgba(99,102,241,0.04)';
    });
  });

  ['dragleave', 'drop'].forEach(function(evt) {
    dropZone.addEventListener(evt, function(e) {
      e.preventDefault();
      e.stopPropagation();
      this.style.borderColor = '';
      this.style.background = '';
    });
  });

  dropZone.addEventListener('drop', function(e) {
    if (e.dataTransfer && e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      handleFile(e.dataTransfer.files);
    }
  });
});
</script>
<?php $__env->stopPush(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/events/form.blade.php ENDPATH**/ ?>