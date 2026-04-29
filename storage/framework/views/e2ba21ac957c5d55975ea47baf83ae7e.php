<nav class="bg-slate-900/85 backdrop-blur-xl sticky top-0 z-50 border-b border-white/10"
   x-data="{ mobileOpen: false }">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex items-center justify-between h-16">
      
      <?php
        $_brandLogoUrl = null;
        if (!empty($siteLogo)) {
          try {
            $_brandLogoUrl = app(\App\Services\StorageManager::class)->resolveUrl($siteLogo);
          } catch (\Throwable) { /* fall through to icon */ }
        }
        $_brandName = $siteName ?: config('app.name');
      ?>
      <a class="font-bold flex items-center gap-2 text-white text-lg" href="<?php echo e(route('home')); ?>">
        <?php if($_brandLogoUrl): ?>
          <img src="<?php echo e($_brandLogoUrl); ?>" alt="<?php echo e($_brandName); ?>"
               class="h-8 w-auto max-w-[140px] object-contain"
               onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('i'),{className:'bi bi-camera2'}));">
        <?php else: ?>
          <i class="bi bi-camera2"></i>
        <?php endif; ?>
        <span><?php echo e($_brandName); ?></span>
      </a>

      
      <button class="lg:hidden text-white/70 hover:text-white p-2" type="button"
          @click="mobileOpen = !mobileOpen" aria-label="Toggle navigation">
        <i class="bi bi-list text-2xl"></i>
      </button>

      
      <div class="hidden lg:flex items-center flex-1 ml-6">
        
        <ul class="flex items-center gap-1">
          <li>
            <a class="px-3 py-2 rounded-lg text-sm font-medium transition <?php echo e(request()->routeIs('home') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white'); ?>"
              href="<?php echo e(route('home')); ?>">
              <i class="bi bi-house-door mr-1"></i><?php echo e(__('nav.home')); ?>

            </a>
          </li>
          <li>
            <a class="px-3 py-2 rounded-lg text-sm font-medium transition <?php echo e(request()->routeIs('events.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white'); ?>"
              href="<?php echo e(route('events.index')); ?>">
              <i class="bi bi-grid-3x3-gap mr-1"></i><?php echo e(__('nav.events')); ?>

            </a>
          </li>
          <li>
            <a class="px-3 py-2 rounded-lg text-sm font-medium transition <?php echo e(request()->routeIs('blog.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white'); ?>"
              href="<?php echo e(route('blog.index')); ?>">
              <i class="bi bi-newspaper mr-1"></i><?php echo e(__('nav.blog')); ?>

            </a>
          </li>
          <li>
            <a class="px-3 py-2 rounded-lg text-sm font-medium transition <?php echo e(request()->routeIs('products.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white'); ?>"
              href="<?php echo e(route('products.index')); ?>">
              <i class="bi bi-box-seam mr-1"></i><?php echo e(__('nav.products')); ?>

            </a>
          </li>
          <li>
            <a class="px-3 py-2 rounded-lg text-sm font-medium transition <?php echo e(request()->routeIs('contact') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white'); ?>"
              href="<?php echo e(route('contact')); ?>">
              <i class="bi bi-envelope mr-1"></i><?php echo e(__('nav.contact')); ?>

            </a>
          </li>
          
          <?php if(auth()->guard()->check()): ?>
            <?php if(!Auth::user()->photographerProfile): ?>
            <li>
              <a class="px-3 py-2 rounded-lg text-sm font-semibold transition <?php echo e(request()->routeIs('sell-photos') ? 'text-white bg-white/10' : 'text-amber-300 hover:text-amber-200'); ?>"
                href="<?php echo e(route('sell-photos')); ?>">
                <i class="bi bi-camera-fill mr-1"></i>เริ่มขายรูป
              </a>
            </li>
            <?php endif; ?>
          <?php else: ?>
            <li>
              <a class="px-3 py-2 rounded-lg text-sm font-semibold transition <?php echo e(request()->routeIs('sell-photos') ? 'text-white bg-white/10' : 'text-amber-300 hover:text-amber-200'); ?>"
                href="<?php echo e(route('sell-photos')); ?>">
                <i class="bi bi-camera-fill mr-1"></i>เริ่มขายรูป
              </a>
            </li>
          <?php endif; ?>
        </ul>

        
        <ul class="ml-auto flex items-center gap-2">
          
          <?php
            $_multilangOn = \App\Http\Controllers\Api\LanguageApiController::isEnabled();
            $_enabledLocales = \App\Http\Controllers\Api\LanguageApiController::enabled();
            $_current = app()->getLocale();
            $_currentMeta = $_enabledLocales[$_current]
                ?? \App\Http\Controllers\Api\LanguageApiController::SUPPORTED[$_current]
                ?? ['native' => 'ไทย', 'flag' => '🇹🇭'];
          ?>
          <?php if($_multilangOn && count($_enabledLocales) > 1): ?>
          <li class="px-1">
            <div x-data="{ open: false }" class="relative">
              <button @click="open = !open" @click.outside="open = false" type="button"
                  class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-sm text-white/70 hover:text-white hover:bg-white/10 transition">
                <span class="text-base leading-none"><?php echo e($_currentMeta['flag']); ?></span>
                <span class="hidden xl:inline font-medium"><?php echo e(strtoupper($_current)); ?></span>
                <i class="bi bi-chevron-down text-[10px]"></i>
              </button>
              <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="absolute right-0 top-full mt-2 w-44 bg-white dark:bg-slate-800 rounded-xl shadow-xl dark:shadow-black/30 border border-gray-100 dark:border-white/10 overflow-hidden z-50">
                <?php $__currentLoopData = $_enabledLocales; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => $meta): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <a href="<?php echo e(route('lang.switch', $code)); ?>?redirect=<?php echo e(urlencode(request()->getRequestUri())); ?>"
                     class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition <?php echo e($_current === $code ? 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 font-semibold' : 'text-gray-700 dark:text-gray-300'); ?>">
                    <span class="text-lg leading-none"><?php echo e($meta['flag']); ?></span>
                    <span class="flex-1"><?php echo e($meta['native']); ?></span>
                    <?php if($_current === $code): ?><i class="bi bi-check-circle-fill text-indigo-600 dark:text-indigo-400"></i><?php endif; ?>
                  </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </div>
            </div>
          </li>
          <?php endif; ?>

          
          <li>
            <button type="button" class="theme-toggle text-white/70 hover:text-white p-2 transition" title="สลับโหมดกลางวัน/กลางคืน">
              <i class="bi bi-moon-fill"></i>
            </button>
          </li>

          <?php if(auth()->guard()->check()): ?>
            
            <li class="relative" x-data="userNotifications()" x-init="init()" @click.outside="open = false">
              <button type="button" @click="toggle()" class="relative text-white/70 hover:text-white px-3 py-2 transition" title="<?php echo e(__('nav.notifications')); ?>">
                <i class="bi bi-bell text-lg" :class="unreadCount > 0 ? 'animate-pulse' : ''"></i>
                <span x-show="unreadCount > 0"
                      x-text="unreadCount > 99 ? '99+' : unreadCount"
                      class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1.5 min-w-[18px] text-center font-semibold"></span>
              </button>

              
              <div x-show="open" x-cloak
                   x-transition:enter="transition ease-out duration-150"
                   x-transition:enter-start="opacity-0 translate-y-1"
                   x-transition:enter-end="opacity-100 translate-y-0"
                   class="absolute right-0 top-full mt-2 w-[360px] bg-white dark:bg-slate-800 rounded-xl shadow-2xl dark:shadow-black/30 border border-gray-100 dark:border-white/10 overflow-hidden z-50">

                
                <div class="flex items-center justify-between p-3 border-b border-gray-100 dark:border-white/10">
                  <h4 class="font-semibold text-gray-800 dark:text-gray-100 text-sm flex items-center gap-2">
                    <i class="bi bi-bell-fill text-indigo-500 dark:text-indigo-400"></i>
                    <?php echo e(__('nav.notifications')); ?>

                    <span x-show="unreadCount > 0"
                          x-text="unreadCount"
                          class="bg-indigo-500 text-white text-[10px] rounded-full px-2 py-0.5 font-medium"></span>
                  </h4>
                  <button type="button" x-show="unreadCount > 0" @click="markAllRead()"
                          class="text-xs text-indigo-500 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium transition">
                    <i class="bi bi-check2-all mr-1"></i>อ่านทั้งหมด
                  </button>
                </div>

                
                <div x-show="loading && notifications.length === 0" class="p-8 text-center">
                  <i class="bi bi-arrow-repeat animate-spin text-2xl text-gray-300 dark:text-gray-600"></i>
                </div>

                
                <template x-if="!loading && notifications.length === 0">
                  <div class="p-8 text-center">
                    <i class="bi bi-bell-slash text-3xl text-gray-300 dark:text-gray-600"></i>
                    <p class="text-gray-400 dark:text-gray-500 text-sm mt-2">ยังไม่มีการแจ้งเตือน</p>
                  </div>
                </template>

                
                <div class="max-h-[400px] overflow-y-auto">
                  <template x-for="n in notifications.slice(0, 8)" :key="n.id">
                    
                    <a :href="n.action_href
                        ? n.action_href
                        : (n.action_url
                            ? (/^https?:\/\//i.test(n.action_url)
                                ? n.action_url
                                : ('/' + n.action_url.replace(/^\//, '')))
                            : '#')"
                       @click="markRead(n.id, $event)"
                       :class="n.is_read ? 'bg-white dark:bg-slate-800' : 'bg-indigo-50/50 dark:bg-indigo-500/10'"
                       class="flex gap-3 p-3 border-b border-gray-50 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/5 transition cursor-pointer">
                      <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 text-sm"
                           :class="typeStyle(n.type).bg + ' ' + typeStyle(n.type).text">
                        <i :class="'bi bi-' + typeStyle(n.type).icon"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-start gap-2">
                          <p class="font-medium text-sm text-gray-800 dark:text-gray-100 line-clamp-1" x-text="n.title"></p>
                          <span x-show="!n.is_read" class="inline-block w-2 h-2 bg-indigo-500 rounded-full shrink-0 mt-1.5"></span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2" x-text="n.message"></p>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1" x-text="timeAgo(n.created_at)"></p>
                      </div>
                    </a>
                  </template>
                </div>

                
                <div class="border-t border-gray-100 dark:border-white/10 p-2 flex gap-1">
                  <a href="<?php echo e(route('notifications.index')); ?>"
                     class="flex-1 px-3 py-2 text-center text-sm text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 rounded-lg transition font-medium">
                    ดูทั้งหมด
                  </a>
                  <a href="<?php echo e(route('profile.notification-preferences')); ?>"
                     class="px-3 py-2 text-gray-400 dark:text-gray-500 hover:bg-gray-50 dark:hover:bg-white/5 rounded-lg transition" title="ตั้งค่า">
                    <i class="bi bi-gear"></i>
                  </a>
                </div>
              </div>
            </li>
            
            <li>
              <a class="relative text-white/70 hover:text-white px-3 py-2 transition" href="<?php echo e(route('wishlist.index')); ?>" title="<?php echo e(__('nav.my_wishlist')); ?>">
                <i class="bi bi-heart text-lg"></i>
                <span class="wishlist-badge absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1 hidden"
                   id="wishlistBadge">0</span>
              </a>
            </li>
            
            <li>
              <a class="relative text-white/70 hover:text-white px-3 py-2 transition" href="<?php echo e(route('cart.index')); ?>" title="<?php echo e(__('nav.cart')); ?>">
                <i class="bi bi-bag text-lg"></i>
                <span class="cart-badge absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1 <?php echo e(($cartCount ?? 0) > 0 ? '' : 'hidden'); ?>">
                  <?php echo e($cartCount ?? 0); ?>

                </span>
              </a>
            </li>
            
            <li class="relative" x-data="{ open: false }" @click.away="open = false">
              <button class="flex items-center gap-2 text-white/70 hover:text-white px-3 py-2 transition text-sm"
                  @click="open = !open">
                <?php if (isset($component)) { $__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.avatar','data' => ['src' => Auth::user()->avatar,'name' => Auth::user()->first_name . ' ' . Auth::user()->last_name,'userId' => Auth::id(),'size' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['src' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::user()->avatar),'name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::user()->first_name . ' ' . Auth::user()->last_name),'user-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::id()),'size' => 'sm']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b)): ?>
<?php $attributes = $__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b; ?>
<?php unset($__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b)): ?>
<?php $component = $__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b; ?>
<?php unset($__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b); ?>
<?php endif; ?>
                <span class="text-sm"><?php echo e(Auth::user()->first_name); ?></span>
                <i class="bi bi-chevron-down text-xs"></i>
              </button>
              <?php
                $_isPhotographer = Auth::user()->photographerProfile !== null;
                $_photographerApproved = $_isPhotographer && Auth::user()->photographerProfile->status === 'approved';
                $_photographerPending = $_isPhotographer && Auth::user()->photographerProfile->status === 'pending';
              ?>
              <div x-show="open"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute right-0 mt-2 bg-white dark:bg-slate-800 rounded-xl shadow-xl dark:shadow-black/30 border border-gray-100 dark:border-white/10 min-w-[260px] py-1 z-50"
                 x-cloak>
                
                <div class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <?php if (isset($component)) { $__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.avatar','data' => ['src' => Auth::user()->avatar,'name' => Auth::user()->first_name . ' ' . Auth::user()->last_name,'userId' => Auth::id(),'size' => 'md']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['src' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::user()->avatar),'name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::user()->first_name . ' ' . Auth::user()->last_name),'user-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::id()),'size' => 'md']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b)): ?>
<?php $attributes = $__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b; ?>
<?php unset($__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b)): ?>
<?php $component = $__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b; ?>
<?php unset($__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b); ?>
<?php endif; ?>
                    <div class="overflow-hidden">
                      <div class="font-semibold truncate text-gray-900 dark:text-gray-100"><?php echo e(Auth::user()->first_name); ?> <?php echo e(Auth::user()->last_name); ?></div>
                      <div class="text-sm text-gray-500 dark:text-gray-400 truncate"><?php echo e(Auth::user()->email); ?></div>
                    </div>
                  </div>
                  <?php if($_photographerApproved): ?>
                  <div class="mt-2">
                    <span class="inline-flex items-center rounded-full bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-500 dark:to-blue-600 text-white text-[10px] px-2.5 py-1">
                      <i class="bi bi-camera mr-1"></i>ช่างภาพ
                    </span>
                  </div>
                  <?php elseif($_photographerPending): ?>
                  <div class="mt-2">
                    <span class="inline-flex items-center rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-300 text-[10px] px-2.5 py-1">
                      <i class="bi bi-hourglass-split mr-1"></i>รอการอนุมัติช่างภาพ
                    </span>
                  </div>
                  <?php endif; ?>
                </div>
                <div class="border-t border-gray-100 dark:border-white/10 my-1"></div>

                
                <?php if(Auth::guard('admin')->check()): ?>
                <div class="px-4 pt-1 pb-1">
                  <span class="text-gray-500 dark:text-gray-400 font-semibold uppercase text-[10px] tracking-wide">ผู้ดูแลระบบ</span>
                </div>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="<?php echo e(route('admin.dashboard')); ?>">
                  <i class="bi bi-shield-lock mr-2 text-red-500 dark:text-red-400"></i>แดชบอร์ดแอดมิน
                </a>
                <div class="border-t border-gray-100 dark:border-white/10 my-1"></div>
                <?php endif; ?>

                
                <?php if($_photographerApproved): ?>
                <div class="px-4 pt-1 pb-1">
                  <span class="text-gray-500 dark:text-gray-400 font-semibold uppercase text-[10px] tracking-wide">ช่างภาพ</span>
                </div>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="<?php echo e(route('photographer.dashboard')); ?>">
                  <i class="bi bi-speedometer2 mr-2 text-blue-600 dark:text-blue-400"></i>แดชบอร์ดช่างภาพ
                </a>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="<?php echo e(route('photographer.events.index')); ?>">
                  <i class="bi bi-calendar-event mr-2 text-cyan-600 dark:text-cyan-400"></i>จัดการอีเวนต์
                </a>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="<?php echo e(route('photographer.earnings')); ?>">
                  <i class="bi bi-wallet2 mr-2 text-emerald-500 dark:text-emerald-400"></i>รายได้ของฉัน
                </a>
                <div class="border-t border-gray-100 dark:border-white/10 my-1"></div>
                <div class="px-4 pt-1 pb-1">
                  <span class="text-gray-500 dark:text-gray-400 font-semibold uppercase text-[10px] tracking-wide">ซื้อรูปภาพ</span>
                </div>
                <?php endif; ?>

                
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="<?php echo e(route('profile')); ?>">
                  <i class="bi bi-grid mr-2 text-indigo-500 dark:text-indigo-400"></i><?php echo e(__('nav.my_account')); ?>

                </a>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="<?php echo e(route('profile.orders')); ?>">
                  <i class="bi bi-receipt mr-2 text-blue-500 dark:text-blue-400"></i><?php echo e(__('nav.my_orders')); ?>

                </a>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="<?php echo e(route('profile.edit')); ?>">
                  <i class="bi bi-person-gear mr-2 text-slate-500 dark:text-slate-400"></i><?php echo e(__('profile.edit_profile')); ?>

                </a>
                <?php if(app(\App\Services\UserStorageService::class)->systemEnabled()): ?>
                  <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="<?php echo e(route('storage.index')); ?>">
                    <i class="bi bi-cloud-fill mr-2 text-sky-500 dark:text-sky-400"></i>คลาวด์ของฉัน
                  </a>
                <?php endif; ?>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="<?php echo e(route('profile.referrals')); ?>">
                  <i class="bi bi-people-fill mr-2 text-rose-500 dark:text-rose-400"></i>แนะนำเพื่อน
                </a>
                <a class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition" href="<?php echo e(route('support.index')); ?>">
                  <i class="bi bi-life-preserver mr-2 text-pink-500 dark:text-pink-400"></i>Support Tickets
                </a>

                
                <?php if($_isPhotographer && $_photographerApproved): ?>
                  
                  <div class="border-t border-gray-100 dark:border-white/10 my-1"></div>
                  <div class="px-4 py-2">
                    <a href="<?php echo e(route('photographer.dashboard')); ?>"
                      class="flex items-center justify-center gap-2 w-full py-2 bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-500 dark:to-purple-500 text-white rounded-[10px] text-[0.82rem] font-semibold hover:from-indigo-700 hover:to-purple-700 transition shadow-md shadow-indigo-600/20">
                      <i class="bi bi-camera"></i> เปลี่ยนเป็นโหมดช่างภาพ
                    </a>
                  </div>
                <?php elseif(!$_isPhotographer): ?>
                  
                  <div class="border-t border-gray-100 dark:border-white/10 my-1"></div>
                  <div class="px-4 py-2">
                    <a href="<?php echo e(route('photographer-onboarding.quick')); ?>"
                      class="flex items-center justify-center gap-2 w-full py-2 bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-500 dark:to-blue-600 text-white rounded-[10px] text-[0.82rem] font-semibold hover:from-blue-700 hover:to-blue-800 dark:hover:from-blue-600 dark:hover:to-blue-500 transition">
                      <i class="bi bi-camera"></i> <?php echo e(__('nav.become_photographer')); ?>

                    </a>
                    <p class="text-[10px] text-center text-gray-500 dark:text-gray-400 mt-1.5 mb-0">
                      ใช้บัญชีเดิม · ไม่ต้องสมัครใหม่
                    </p>
                  </div>
                <?php endif; ?>

                <div class="border-t border-gray-100 dark:border-white/10 my-1"></div>
                <form method="POST" action="<?php echo e(route('auth.logout')); ?>">
                  <?php echo csrf_field(); ?>
                  <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-500 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                    <i class="bi bi-box-arrow-right mr-2"></i><?php echo e(__('nav.logout')); ?>

                  </button>
                </form>
              </div>
            </li>
          <?php else: ?>
            <li class="hidden lg:block">
              <a class="text-white/70 hover:text-white px-3 py-2 rounded-lg text-sm font-medium transition" href="<?php echo e(route('auth.register')); ?>"><?php echo e(__('nav.register')); ?></a>
            </li>
            <li>
              <a class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white font-semibold px-6 py-2 rounded-full text-sm hover:from-indigo-600 hover:to-indigo-700 transition inline-flex items-center gap-1"
                href="<?php echo e(route('auth.login')); ?>">
                <i class="bi bi-person mr-1"></i><?php echo e(__('nav.login')); ?>

              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    
    <div class="lg:hidden" x-show="mobileOpen" x-collapse x-cloak>
      <div class="pb-4 pt-2 border-t border-white/10">
        <ul class="space-y-1">
          <li>
            <a class="block px-3 py-2 rounded-lg text-sm font-medium transition <?php echo e(request()->routeIs('home') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white'); ?>"
              href="<?php echo e(route('home')); ?>">
              <i class="bi bi-house-door mr-1"></i><?php echo e(__('nav.home')); ?>

            </a>
          </li>
          <li>
            <a class="block px-3 py-2 rounded-lg text-sm font-medium transition <?php echo e(request()->routeIs('events.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white'); ?>"
              href="<?php echo e(route('events.index')); ?>">
              <i class="bi bi-grid-3x3-gap mr-1"></i><?php echo e(__('nav.events')); ?>

            </a>
          </li>
          <li>
            <a class="block px-3 py-2 rounded-lg text-sm font-medium transition <?php echo e(request()->routeIs('blog.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white'); ?>"
              href="<?php echo e(route('blog.index')); ?>">
              <i class="bi bi-newspaper mr-1"></i><?php echo e(__('nav.blog')); ?>

            </a>
          </li>
          <li>
            <a class="block px-3 py-2 rounded-lg text-sm font-medium transition <?php echo e(request()->routeIs('products.*') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white'); ?>"
              href="<?php echo e(route('products.index')); ?>">
              <i class="bi bi-box-seam mr-1"></i><?php echo e(__('nav.products')); ?>

            </a>
          </li>
          <li>
            <a class="block px-3 py-2 rounded-lg text-sm font-medium transition <?php echo e(request()->routeIs('contact') ? 'text-white bg-white/10' : 'text-white/70 hover:text-white'); ?>"
              href="<?php echo e(route('contact')); ?>">
              <i class="bi bi-envelope mr-1"></i><?php echo e(__('nav.contact')); ?>

            </a>
          </li>
        </ul>

        
        <?php if($_multilangOn && count($_enabledLocales) > 1): ?>
        <div class="mt-3 px-3 border-t border-white/10 pt-3">
          <p class="text-white/50 text-xs uppercase tracking-wide mb-2">Language / ภาษา</p>
          <div class="flex gap-2">
            <?php $__currentLoopData = $_enabledLocales; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => $meta): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <a href="<?php echo e(route('lang.switch', $code)); ?>?redirect=<?php echo e(urlencode(request()->getRequestUri())); ?>"
                 class="flex-1 flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg text-sm transition
                    <?php echo e(app()->getLocale() === $code ? 'bg-white/20 text-white' : 'bg-white/5 text-white/60 hover:bg-white/10'); ?>">
                <span><?php echo e($meta['flag']); ?></span>
                <span class="text-xs font-medium"><?php echo e(strtoupper($code)); ?></span>
              </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="border-t border-white/10 mt-3 pt-3">
          <?php if(auth()->guard()->check()): ?>
            <div class="flex items-center gap-2 px-3 py-2">
              <?php if (isset($component)) { $__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.avatar','data' => ['src' => Auth::user()->avatar,'name' => Auth::user()->first_name . ' ' . Auth::user()->last_name,'userId' => Auth::id(),'size' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['src' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::user()->avatar),'name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::user()->first_name . ' ' . Auth::user()->last_name),'user-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::id()),'size' => 'sm']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b)): ?>
<?php $attributes = $__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b; ?>
<?php unset($__attributesOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b)): ?>
<?php $component = $__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b; ?>
<?php unset($__componentOriginal8ca5b43b8fff8bb34ab2ba4eb4bdd67b); ?>
<?php endif; ?>
              <span class="text-white text-sm font-medium"><?php echo e(Auth::user()->first_name); ?> <?php echo e(Auth::user()->last_name); ?></span>
            </div>
            <ul class="space-y-1 mt-2">
              <li>
                <a class="block px-3 py-2 text-white/70 hover:text-white text-sm transition" href="<?php echo e(route('profile')); ?>">
                  <i class="bi bi-grid mr-2"></i><?php echo e(__('nav.my_account')); ?>

                </a>
              </li>
              <li>
                <a class="block px-3 py-2 text-white/70 hover:text-white text-sm transition" href="<?php echo e(route('profile.orders')); ?>">
                  <i class="bi bi-receipt mr-2"></i><?php echo e(__('nav.my_orders')); ?>

                </a>
              </li>
              <li>
                <a class="block px-3 py-2 text-white/70 hover:text-white text-sm transition" href="<?php echo e(route('profile.referrals')); ?>">
                  <i class="bi bi-people-fill mr-2"></i>แนะนำเพื่อน
                </a>
              </li>
              <li>
                <form method="POST" action="<?php echo e(route('auth.logout')); ?>">
                  <?php echo csrf_field(); ?>
                  <button type="submit" class="block w-full text-left px-3 py-2 text-red-400 hover:text-red-300 text-sm transition">
                    <i class="bi bi-box-arrow-right mr-2"></i><?php echo e(__('nav.logout')); ?>

                  </button>
                </form>
              </li>
            </ul>
          <?php else: ?>
            <div class="flex items-center gap-3 px-3 mt-2">
              <a class="text-white/70 hover:text-white text-sm transition" href="<?php echo e(route('auth.register')); ?>"><?php echo e(__('nav.register')); ?></a>
              <a class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white font-semibold px-6 py-2 rounded-full text-sm inline-flex items-center gap-1"
                href="<?php echo e(route('auth.login')); ?>">
                <i class="bi bi-person mr-1"></i><?php echo e(__('nav.login')); ?>

              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</nav>

<?php if(auth()->guard()->check()): ?>
<script>
  function userNotifications() {
    return {
      open: false,
      notifications: [],
      unreadCount: 0,
      loading: false,
      lastCheck: null,
      pollInterval: null,

      init() {
        this.fetchUnreadCount();
        // Poll every 30 seconds — gated on document.hidden so a tab
        // that's been backgrounded for hours doesn't keep hammering
        // the API. We still re-fetch immediately when the tab becomes
        // visible again so the badge isn't stale on focus.
        this.pollInterval = setInterval(() => {
          if (!document.hidden) this.fetchUnreadCount();
        }, 30000);

        document.addEventListener('visibilitychange', () => {
          if (!document.hidden) this.fetchUnreadCount();
        });
      },

      toggle() {
        this.open = !this.open;
        if (this.open && this.notifications.length === 0) {
          this.fetch();
        }
      },

      async fetch() {
        this.loading = true;
        try {
          const response = await fetch('/api/notifications', { headers: { 'Accept': 'application/json' } });
          if (response.ok) {
            const data = await response.json();
            this.notifications = data.notifications || [];
            this.unreadCount = data.unread_count || 0;
          }
        } catch (e) { console.error('Failed to fetch notifications', e); }
        this.loading = false;
      },

      async fetchUnreadCount() {
        try {
          const response = await fetch('/api/notifications/unread-count', { headers: { 'Accept': 'application/json' } });
          if (response.ok) {
            const data = await response.json();
            this.unreadCount = data.unread_count || 0;
          }
        } catch (e) {}
      },

      async markRead(id, event) {
        // Mark as read in background, allow navigation
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        try {
          await fetch(`/api/notifications/${id}/read`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
          });
          // Update local state
          const n = this.notifications.find(x => x.id === id);
          if (n && !n.is_read) {
            n.is_read = true;
            this.unreadCount = Math.max(0, this.unreadCount - 1);
          }
        } catch (e) {}
      },

      async markAllRead() {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        try {
          await fetch('/api/notifications/read-all', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
          });
          this.notifications.forEach(n => n.is_read = true);
          this.unreadCount = 0;
        } catch (e) {}
      },

      typeStyle(type) {
        const styles = {
          order:                { icon: 'cart-check', bg: 'bg-blue-100 dark:bg-blue-500/20', text: 'text-blue-600 dark:text-blue-300' },
          payment_approved:     { icon: 'check-circle', bg: 'bg-emerald-100 dark:bg-emerald-500/20', text: 'text-emerald-600 dark:text-emerald-300' },
          payment_rejected:     { icon: 'x-circle', bg: 'bg-red-100 dark:bg-red-500/20', text: 'text-red-600 dark:text-red-300' },
          download_ready:       { icon: 'download', bg: 'bg-indigo-100 dark:bg-indigo-500/20', text: 'text-indigo-600 dark:text-indigo-300' },
          refund:               { icon: 'arrow-counterclockwise', bg: 'bg-amber-100 dark:bg-amber-500/20', text: 'text-amber-600 dark:text-amber-300' },
          new_sale:             { icon: 'cash-coin', bg: 'bg-emerald-100 dark:bg-emerald-500/20', text: 'text-emerald-600 dark:text-emerald-300' },
          payout:               { icon: 'wallet2', bg: 'bg-purple-100 dark:bg-purple-500/20', text: 'text-purple-600 dark:text-purple-300' },
          review:               { icon: 'star-fill', bg: 'bg-yellow-100 dark:bg-yellow-500/20', text: 'text-yellow-600 dark:text-yellow-300' },
          photographer_approved:{ icon: 'camera', bg: 'bg-indigo-100 dark:bg-indigo-500/20', text: 'text-indigo-600 dark:text-indigo-300' },
          contact:              { icon: 'chat-dots', bg: 'bg-pink-100 dark:bg-pink-500/20', text: 'text-pink-600 dark:text-pink-300' },
          system:               { icon: 'megaphone', bg: 'bg-slate-100 dark:bg-slate-500/20', text: 'text-slate-600 dark:text-slate-300' },
          welcome:              { icon: 'emoji-smile', bg: 'bg-green-100 dark:bg-green-500/20', text: 'text-green-600 dark:text-green-300' },
        };
        return styles[type] || { icon: 'bell', bg: 'bg-gray-100 dark:bg-gray-500/20', text: 'text-gray-600 dark:text-gray-300' };
      },

      timeAgo(dt) {
        if (!dt) return '';
        const now = new Date();
        const date = new Date(dt);
        const diff = Math.floor((now - date) / 1000);
        if (diff < 60) return 'เมื่อสักครู่';
        if (diff < 3600) return Math.floor(diff/60) + ' นาทีที่แล้ว';
        if (diff < 86400) return Math.floor(diff/3600) + ' ชั่วโมงที่แล้ว';
        if (diff < 604800) return Math.floor(diff/86400) + ' วันที่แล้ว';
        return date.toLocaleDateString('th-TH');
      },
    };
  }
</script>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/layouts/partials/navbar.blade.php ENDPATH**/ ?>