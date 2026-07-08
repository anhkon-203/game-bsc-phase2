<?php
$admin_url = admin_url('admin.php?page=game-bsc-settings');
global $admin_url;

function game_bsc_settings_page() {
    $stages = get_option('game_bsc_stages', []);
    if (!is_array($stages)) $stages = [];

    $rules = get_option('game_bsc_rules', []);
    if (!is_array($rules)) $rules = [];

    $rewards_descriptions = get_option('game_bsc_rewards_descriptions', []);
    if (!is_array($rewards_descriptions)) $rewards_descriptions = [];

    $terms = get_option('game_bsc_terms', []);
    if (!is_array($terms)) $terms = [];
    ?>
    <div class="wrap">
        <h1><?php _e('Cài đặt Game BSC', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h1>

        <?php if (!empty($_GET['import_result'])): ?>
            <div class="notice notice-success"><p><?php echo esc_html($_GET['import_result']); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($_GET['import_error'])): ?>
            <div class="notice notice-error"><p><?php echo esc_html($_GET['import_error']); ?></p></div>
        <?php endif; ?>

        <!-- ========== TABS NAVIGATION ========== -->
        <div class="nav-tab-wrapper wg-game-tabs" style="margin: 15px 0; border-bottom: 1px solid #ccc;">
            <a href="#tab-general" class="nav-tab nav-tab-active" data-tab="general">
                <?php _e('Cài đặt chung', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
            </a>
            <a href="#tab-rules" class="nav-tab" data-tab="rules">
                <?php _e('Thể lệ chương trình', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
            </a>
            <a href="#tab-rewards" class="nav-tab" data-tab="rewards">
                <?php _e('Cơ chế đổi quà', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
            </a>
            <a href="#tab-api-url" class="nav-tab" data-tab="api-url">
                <?php _e('Url API', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
            </a>
            <a href="#tab-voucher" class="nav-tab" data-tab="voucher">
                <?php _e('Cài đặt Voucher', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
            </a>
            <a href="#tab-banners" class="nav-tab" data-tab="banners">
                <?php _e('Quản lí banner', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
            </a>
            <a href="#tab-terms" class="nav-tab" data-tab="terms">
                <?php _e('Điều khoản đổi quà', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
            </a>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wg-game-settings-form">
            <?php wp_nonce_field('game_bsc_save_settings'); ?>
            <input type="hidden" name="action" value="game_bsc_save_settings">

            <!-- ========== TAB 1: GENERAL SETTINGS ========== -->
            <div id="tab-general" class="wg-game-tab-content wg-game-tab-active" style="display:block;">
                <h2><?php _e('Thời gian diễn ra game', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="start_date"><?php _e('Từ ngày', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td><input type="date" name="start_date" id="start_date" value="<?php echo esc_attr(get_option('game_bsc_start_date')); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="end_date"><?php _e('Đến ngày', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td><input type="date" name="end_date" id="end_date" value="<?php echo esc_attr(get_option('game_bsc_end_date')); ?>"></td>
                    </tr>

<!--                    thời gian từ .. giờ đến .. giờ-->
                    <tr>
                        <th><label for="daily_start_time"><?php _e('Thời gian chơi trong ngày - Từ giờ', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td><input type="time" name="daily_start_time" id="daily_start_time" value="<?php echo esc_attr(get_option('game_bsc_daily_start_time', '00:00')); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="daily_end_time"><?php _e('Thời gian chơi trong ngày - Đến giờ', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td><input type="time" name="daily_end_time" id="daily_end_time" value="<?php echo esc_attr(get_option('game_bsc_daily_end_time', '23:59')); ?>"></td>
                    </tr>
                </table>

                <h2><?php _e('Cài đặt mỗi chặng', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>
                <div id="stages">
                    <?php foreach ($stages as $i => $stage): ?>
                        <fieldset class="stage-item" style="border:1px solid #ccc; margin-bottom:10px; padding:10px;">
                            <legend><?php echo sprintf(__('Chặng %d', WG_GAME_PLUGIN_TEXTDOMAIN), $i+1); ?></legend>
                            <?php /* Hidden: Từ ngày field */ ?>
                            <?php /* <label><?php _e('Từ ngày', WG_GAME_PLUGIN_TEXTDOMAIN); ?> <input type="date" name="stages[<?php echo $i; ?>][from]" value="<?php echo esc_attr($stage['from'] ?? ''); ?>"></label> */ ?>
                            <?php /* Hidden: Đến ngày field */ ?>
                            <?php /* <label><?php _e('Đến ngày', WG_GAME_PLUGIN_TEXTDOMAIN); ?> <input type="date" name="stages[<?php echo $i; ?>][to]" value="<?php echo esc_attr($stage['to'] ?? ''); ?>"></label> */ ?>

                                <!--      Từ chặng đến chang                      -->
                            <label>
                                Từ chặng
                                <input type="number" style="width: 5%" name="stages[<?php echo $i; ?>][from_stage]" value="<?php echo esc_attr($stage['from_stage'] ?? ''); ?>">
                            </label>
                            <label>
                                Đến chặng
                                <input type="number" style="width: 5%" name="stages[<?php echo $i; ?>][to_stage]" value="<?php echo esc_attr($stage['to_stage'] ?? ''); ?>">
                            </label>
                            <?php /* Hidden: Thời gian trả lời field */ ?>
                            <?php /* <label><?php _e('Thời gian trả lời 1 câu hỏi (giây)', WG_GAME_PLUGIN_TEXTDOMAIN); ?> <input type="number" name="stages[<?php echo $i; ?>][duration]" value="<?php echo esc_attr($stage['duration'] ?? ''); ?>"></label> */ ?>
                            <label><?php _e('Điểm tặng mỗi câu', WG_GAME_PLUGIN_TEXTDOMAIN); ?> <input type="number" name="stages[<?php echo $i; ?>][score]" value="<?php echo esc_attr($stage['score'] ?? ''); ?>"></label>
                            <label><?php _e('Số câu hỏi/ngày', WG_GAME_PLUGIN_TEXTDOMAIN); ?> <input type="number" name="stages[<?php echo $i; ?>][questions_per_day]" value="<?php echo esc_attr($stage['questions_per_day'] ?? ''); ?>"></label>
                            <button type="button" class="button remove-stage"><?php _e('Xóa chặng', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                        </fieldset>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="add-stage" class="button"><?php _e('Thêm chặng', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>

                <!-- <h2><?php _e('Số lần được phép trả lời sai / ngày', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="max_wrong_answers"><?php _e('Số lần trả lời sai tối đa', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td><input type="number" name="max_wrong_answers" id="max_wrong_answers" value="<?php echo esc_attr(get_option('game_bsc_max_wrong_answers', 0)); ?>"></td>
                    </tr>
                </table> -->

                <h2><?php _e('Số mảnh rơi tối đa / ngày của toàn hệ thống. Mặc định: Không giới hạn', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="max_drop_pieces_per_day"><?php _e('Số mảnh tối đa', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td><input type="number" name="max_drop_pieces_per_day" id="max_drop_pieces_per_day" value="<?php echo esc_attr(get_option('game_bsc_max_drop_pieces_per_day', 0)); ?>"></td>
                    </tr>
                </table>

                <h2><?php _e('Số mảnh rơi tối đa / ngày của 1 người chơi. Mặc định: 3 mảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="max_user_drop_pieces_per_day"><?php _e('Số mảnh tối đa', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td><input type="number" name="max_user_drop_pieces_per_day" id="max_user_drop_pieces_per_day" value="<?php echo esc_attr(get_option('game_bsc_max_user_drop_pieces_per_day', 3)); ?>"></td>
                    </tr>
                </table>

                <!--                -->
                <h2><?php _e('Tỉ lệ rơi mảnh(%) - Mặc định 30% ', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>

                <table class="form-table">
                    <tr>
                        <th><label for="piece_drop_rate"><?php _e('Tỉ lệ rơi mảnh (%)', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td><input type="number" name="piece_drop_rate" id="piece_drop_rate" value="<?php echo esc_attr(get_option('game_bsc_piece_drop_rate', 30)); ?>" min="0" max="100"> %</td>
                    </tr>
                </table>

                <!--                -->
                <h2><?php _e('Số ngày được phép chơi tiếp khi đã hết thời gian diễn ra game', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>

                <table class="form-table">
                    <tr>
                        <th><label for="day_allowed_to_play_game_after_period_ends"><?php _e('Số ngày', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td><input type="number" name="day_allowed_to_play_game_after_period_ends" id="day_allowed_to_play_game_after_period_ends" value="<?php echo esc_attr(get_option('game_bsc_day_allowed_to_play_game_after_period_ends', 0)); ?>"></td>
                    </tr>
                </table>

                <?php
                $tasks = get_option('game_bsc_tasks', []);
                $mission_codes = include GAME_BSC_PLUGIN_DIR . 'config/missions.php';
                ?>
                <h2 style="margin-top: 30px; padding-bottom: 10px; border-bottom: 2px solid #0073aa; color: #0073aa;">
                    <span style="background: #fff; padding-right: 15px;">
                        <?php _e('Quản lý nhiệm vụ', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                    </span>
                </h2>
                <div style="overflow-x: auto; max-width: 100%; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 5px;">
                <table class="widefat striped" style="min-width: 1200px; border: 1px solid #ddd; background: #fff;">
                    <thead style="background: linear-gradient(to bottom, #f5f5f5, #e8e8e8); border-bottom: 2px solid #0073aa;">
                    <tr>
                        <th style="width: 12%; padding: 12px 10px; font-weight: 600; color: #23282d; text-align: left; border-right: 1px solid #ddd;">
                            <span style="display: block; font-size: 11px; color: #666; font-weight: normal;">① Nhiệm vụ</span>
                            <?php _e('Tên nhiệm vụ', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                        </th>
                        <th style="width: 13%; padding: 12px 10px; font-weight: 600; color: #23282d; text-align: left; border-right: 1px solid #ddd;">
                            <span style="display: block; font-size: 11px; color: #666; font-weight: normal;">② Hiển thị</span>
                            <?php _e('Tên hiển thị', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                        </th>
                        <th style="width: 10%; padding: 12px 10px; font-weight: 600; color: #23282d; text-align: center; border-right: 1px solid #ddd;">
                            <span style="display: block; font-size: 11px; color: #666; font-weight: normal;">③ Phần thưởng</span>
                            <?php _e('Lượt chơi', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                        </th>
                        <th style="width: 15%; padding: 12px 10px; font-weight: 600; color: #23282d; text-align: left; border-right: 1px solid #ddd;">
                            <span style="display: block; font-size: 11px; color: #666; font-weight: normal;">④ API</span>
                            <?php _e('Đường dẫn thực hiện nhiệm vụ', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                        </th>
                        <th style="width: 20%; padding: 12px 10px; font-weight: 600; color: #23282d; text-align: left; border-right: 1px solid #ddd;">
                            <span style="display: block; font-size: 11px; color: #666; font-weight: normal;">⑤ Mô tả</span>
                            <?php _e('Hướng dẫn nhiệm vụ ( lưu ý)', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                        </th>
                        <th style="width: 30%; padding: 12px 10px; font-weight: 600; color: #23282d; text-align: left;">
                            <span style="display: block; font-size: 11px; color: #666; font-weight: normal;">⑥ Hướng dẫn</span>
                            <?php _e('Hướng dẫn thực hiện', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                        </th>
                    </tr>
                    </thead>
                    <tbody style="background: #fff;">
                    <?php foreach ($mission_codes as $i => $mission):
                        $task = $tasks[$mission['code']] ?? [
                                'title' => '',
                                'reward_spins' => '',
                                'api_url' => '',
                                'description' => '',
                                'amount_required' => '',
                                'guide_note' => '',
                                'guide_delay' => '',
                                'guide_steps' => [],
                        ];
                        ?>
                        <tr style="border-bottom: 1px solid #e5e5e5; transition: background-color 0.2s;">
                            <td style="padding: 15px 10px; vertical-align: top; border-right: 1px solid #e5e5e5;">
                                <label for="task-code-<?php echo esc_attr($mission['code']); ?>" style="display: block; font-weight: 600; color: #0073aa; font-size: 13px; line-height: 1.4;">
                                    <?php echo esc_html($mission['title']); ?>
                                </label>
                                <input type="hidden" id="task-code-<?php echo esc_attr($mission['code']); ?>" name="tasks[<?php echo esc_attr($mission['code']); ?>][code]" value="<?php echo esc_attr($mission['code']); ?>">
                            </td>
                            <td style="padding: 15px 10px; vertical-align: top; border-right: 1px solid #e5e5e5;">
                                <input type="text"
                                       id="task-title-<?php echo esc_attr($mission['code']); ?>"
                                       name="tasks[<?php echo esc_attr($mission['code']); ?>][title]"
                                       value="<?php echo esc_attr($task['title']); ?>"
                                       placeholder="Nhập tên hiển thị..."
                                       style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 3px; font-size: 13px; transition: border-color 0.2s;"
                                       onfocus="this.style.borderColor='#0073aa';"
                                       onblur="this.style.borderColor='#ddd';">
                            </td>
                            <td style="padding: 15px 10px; vertical-align: top; text-align: center; border-right: 1px solid #e5e5e5;">
                                <input type="number"
                                       id="task-reward-<?php echo esc_attr($mission['code']); ?>"
                                       name="tasks[<?php echo esc_attr($mission['code']); ?>][reward_spins]"
                                       value="<?php echo esc_attr($task['reward_spins']); ?>"
                                       placeholder="0"
                                       min="0"
                                       style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 3px; font-size: 13px; text-align: center; transition: border-color 0.2s;"
                                       onfocus="this.style.borderColor='#0073aa';"
                                       onblur="this.style.borderColor='#ddd';">
                            </td>
                            <td style="padding: 15px 10px; vertical-align: top; border-right: 1px solid #e5e5e5;">
                                <input type="text"
                                       id="task-api-<?php echo esc_attr($mission['code']); ?>"
                                       name="tasks[<?php echo esc_attr($mission['code']); ?>][api_url]"
                                       value="<?php echo esc_attr($task['api_url']); ?>"
                                       placeholder="https://..."
                                       style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 3px; font-size: 13px; font-family: monospace; transition: border-color 0.2s;"
                                       onfocus="this.style.borderColor='#0073aa';"
                                       onblur="this.style.borderColor='#ddd';">
                            </td>
                            <td style="padding: 15px 10px; vertical-align: top; border-right: 1px solid #e5e5e5;">
                                <?php
                                wp_editor(
                                        $task['guide_note'] ?? '',
                                        'task-guide-note-' . esc_attr($mission['code']),
                                        [
                                                'textarea_name' => "tasks[" . esc_attr($mission['code']) . "][guide_note]",
                                                'textarea_rows' => 3,
                                                'media_buttons' => true,
                                        ]
                                );
                                ?>
                                        <!--  nap tien lan dau                              -->

                                    <?php if ($mission['code'] === FIRST_DEPOSIT_CODE): ?>
                                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                                            <label for="task-amount-<?php echo esc_attr($mission['code']); ?>" style="display: block; font-weight: bold; margin-bottom: 5px;">
                                                <?php _e('Số tiền yêu cầu (VND)', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                                            </label>
                                            <input type="number"
                                                   id="task-amount-<?php echo esc_attr($mission['code']); ?>"
                                                   name="tasks[<?php echo esc_attr($mission['code']); ?>][amount_required]"
                                                   value="<?php echo !empty($task['amount_required']) ? esc_attr($task['amount_required']) : ''; ?>"
                                                   style="width:100%; padding: 5px; border: 1px solid #ccc;"
                                                   min="0">
                                        </div>
                                <?php endif; ?>
                                    <!--      trade                          -->
                                <?php if ($mission['code'] === TRADE_100M_VND_CODE): ?>
                                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                                        <label for="task-amount-<?php echo esc_attr($mission['code']); ?>" style="display: block; font-weight: bold; margin-bottom: 5px;">
                                            <?php _e('Số tiền yêu cầu (VND)', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                                        </label>
                                        <input type="number"
                                               id="task-amount-<?php echo esc_attr($mission['code']); ?>"
                                               name="tasks[<?php echo esc_attr($mission['code']); ?>][amount_required]"
                                               value="<?php echo !empty($task['amount_required']) ? esc_attr($task['amount_required']) : ''; ?>"
                                               style="width:100%; padding: 5px; border: 1px solid #ccc;"
                                               min="0">
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px 10px; vertical-align: top; background: #fafafa;">
                                <!-- Hướng dẫn nhiệm vụ -->
                                <div style="border: 1px solid #d0d0d0; padding: 12px; background: #fff; border-radius: 4px; max-height: 550px; overflow-y: auto; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">

                                    <!-- Độ trễ -->
                                    <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #ddd;">
                                        <label for="task-guide-delay-<?php echo esc_attr($mission['code']); ?>" style="display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 12px;">
                                            <span style="color: #0073aa;">⏱</span> <?php _e('Độ trễ (ms)', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                                        </label>
                                        <input type="text"
                                               id="task-guide-delay-<?php echo esc_attr($mission['code']); ?>"
                                               name="tasks[<?php echo esc_attr($mission['code']); ?>][guide_delay]"
                                               value="<?php echo esc_attr($task['guide_delay'] ?? ''); ?>"
                                               placeholder="1000"
                                               style="width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 3px; font-size: 13px; transition: border-color 0.2s;"
                                               onfocus="this.style.borderColor='#0073aa';"
                                               onblur="this.style.borderColor='#ddd';">
                                    </div>

                                    <!-- Mô tả hướng dẫn (Repeater) -->
                                    <div style="margin-bottom: 8px;">
                                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #555; font-size: 12px;">
                                            <span style="color: #0073aa;">📋</span> <?php _e('Các bước hướng dẫn', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                                        </label>
                                        <div class="guide-steps-repeater" data-mission-code="<?php echo esc_attr($mission['code']); ?>">
                                            <?php
                                            $guide_steps = $task['guide_steps'] ?? [];
                                            if (empty($guide_steps)) {
                                                $guide_steps = [['content' => '']];
                                            }
                                            foreach ($guide_steps as $step_index => $step):
                                            ?>
                                                <div class="guide-step-item" style="border: 1px solid #d5d5d5; padding: 10px; margin-bottom: 8px; background: #f9f9f9; border-radius: 3px; border-left: 3px solid #0073aa;">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                        <strong style="color: #0073aa; font-size: 12px; display: flex; align-items: center; gap: 5px;">
                                                            <span style="background: #0073aa; color: #fff; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px;">
                                                                <?php echo $step_index + 1; ?>
                                                            </span>
                                                            <?php printf(__('Bước %d', WG_GAME_PLUGIN_TEXTDOMAIN), $step_index + 1); ?>
                                                        </strong>
                                                        <button type="button" class="button button-small remove-guide-step" style="background: #dc3545; color: white; border-color: #dc3545; padding: 3px 10px; height: auto; line-height: 1.4; font-size: 11px; border-radius: 3px; cursor: pointer;">
                                                            <?php _e('✕ Xóa', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                                                        </button>
                                                    </div>
                                                    <?php
                                                    wp_editor(
                                                        $step['content'] ?? '',
                                                        'task-guide-step-' . esc_attr($mission['code']) . '-' . $step_index,
                                                        [
                                                            'textarea_name' => "tasks[" . esc_attr($mission['code']) . "][guide_steps][" . $step_index . "][content]",
                                                            'textarea_rows' => 3,
                                                            'media_buttons' => true,
                                                        ]
                                                    );
                                                    ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="button button-secondary add-guide-step" data-mission-code="<?php echo esc_attr($mission['code']); ?>" style="width: 100%; padding: 6px 12px; border: 1px dashed #0073aa; background: #f0f8ff; color: #0073aa; font-weight: 600; font-size: 12px; border-radius: 3px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#e6f2ff'; this.style.borderStyle='solid';" onmouseout="this.style.background='#f0f8ff'; this.style.borderStyle='dashed';">
                                            <?php _e('➕ Thêm bước hướng dẫn', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- ========== TAB 2: RULES ========== -->
            <div id="tab-rules" class="wg-game-tab-content" style="display:none;">
                <h2><?php _e('Thể lệ chương trình', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>
                <p><?php _e('Quản lý các thể lệ và quy định của chương trình trò chơi', WG_GAME_PLUGIN_TEXTDOMAIN); ?></p>
                <div id="rules-repeater">
                    <?php
                    if (empty($rules)) {
                        $rules = [['title' => '', 'content' => '']];
                    }
                    foreach ($rules as $index => $rule):
                        ?>
                        <fieldset class="rule-item" style="border:2px solid #0073aa; margin-bottom:15px; padding:15px; background:#f9f9f9; border-radius:5px;">
                            <legend style="padding:0 10px; color:#0073aa; font-weight:bold;">
                                <?php printf(__('Thể lệ #%d', WG_GAME_PLUGIN_TEXTDOMAIN), $index + 1); ?>
                            </legend>

                            <div class="form-group" style="margin-bottom:15px;">
                                <label for="rule_title_<?php echo $index; ?>" style="display:block; margin-bottom:5px; font-weight:bold;">
                                    <?php _e('Tiêu đề thể lệ', WG_GAME_PLUGIN_TEXTDOMAIN); ?> <span style="color:red;">*</span>
                                </label>
                                <input
                                        type="text"
                                        id="rule_title_<?php echo $index; ?>"
                                        name="rules[<?php echo $index; ?>][title]"
                                        value="<?php echo esc_attr($rule['title'] ?? ''); ?>"
                                        class="regular-text"
                                        placeholder="<?php _e('Nhập tiêu đề thể lệ', WG_GAME_PLUGIN_TEXTDOMAIN); ?>"
                                >
                            </div>

                            <div class="form-group" style="margin-bottom:15px;">
                                <label for="rule_content_<?php echo $index; ?>" style="display:block; margin-bottom:5px; font-weight:bold;">
                                    <?php _e('Nội dung thể lệ', WG_GAME_PLUGIN_TEXTDOMAIN); ?> <span style="color:red;">*</span>
                                </label>
                                <?php
                                wp_editor(
                                        isset($rule['content']) ? stripslashes($rule['content']) : '',
                                        'rule_content_' . $index,
                                        [
                                                'textarea_name' => 'rules[' . $index . '][content]',
                                                'media_buttons' => true,
                                                'teeny' => true,
                                                'quicktags' => true,
                                        ]
                                );
                                ?>
                            </div>

                            <button type="button" class="button button-danger remove-rule" style="background:#dc3545; color:white; border-color:#dc3545; cursor:pointer; padding:5px 10px;">
                                <?php _e('Xóa thể lệ này', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                            </button>
                        </fieldset>
                    <?php endforeach; ?>
                </div>

                <p>
                    <button type="button" id="add-rule" class="button button-secondary" style="margin-bottom:20px;">
                        <?php _e('+ Thêm thể lệ', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                    </button>
                </p>
            </div>

            <!-- ========== TAB 3: REWARDS ========== -->
            <div id="tab-rewards" class="wg-game-tab-content" style="display:none;">
                <h2><?php _e('Cơ chế đổi quà', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>
                <p><?php _e('Quản lý mô tả chi tiết cơ chế đổi quà bằng điểm và mảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></p>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <!-- Rewards by Points -->
                    <div class="reward-section" style="border:1px solid #2271b1; padding:20px; border-radius:5px; background:#f0f6fc;">
                        <h3 style="color:#2271b1; margin-top:0;">
                            <?php _e('🎯 Đổi Quà Bằng Điểm', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                        </h3>
                        <p style="color:#666; font-size:13px;">
                            <?php _e('Mô tả chi tiết cơ chế và quy trình để người chơi đổi quà bằng điểm tích lũy', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                        </p>
                        <label style="display:block; margin-bottom:5px; font-weight:bold;">
                            <?php _e('Nội dung hướng dẫn (Điểm)', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                        </label>
                        <?php
                        wp_editor(
                                isset($rewards_descriptions['points']) ? stripslashes($rewards_descriptions['points']) : '',
                                'rewards_desc_points',
                                [
                                        'textarea_name' => 'rewards_descriptions[points]',
                                        'media_buttons' => true,
                                        'teeny' => false,
                                        'quicktags' => true,
                                        'textarea_rows' => 8,
                                ]
                        );
                        ?>
                    </div>

                    <!-- Rewards by Pieces -->
                    <div class="reward-section" style="border:1px solid #135e96; padding:20px; border-radius:5px; background:#f0f4f8;">
                        <h3 style="color:#135e96; margin-top:0;">
                            <?php _e('🧩 Đổi Quà Bằng Mảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                        </h3>
                        <p style="color:#666; font-size:13px;">
                            <?php _e('Mô tả chi tiết cơ chế ghép mảnh và quy trình để người chơi đổi quà bằng mảnh ghép', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                        </p>
                        <label style="display:block; margin-bottom:5px; font-weight:bold;">
                            <?php _e('Nội dung hướng dẫn (Mảnh)', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                        </label>
                        <?php
                        wp_editor(
                                isset($rewards_descriptions['pieces']) ? stripslashes($rewards_descriptions['pieces']) : '',
                                'rewards_desc_pieces',
                                [
                                        'textarea_name' => 'rewards_descriptions[pieces]',
                                        'media_buttons' => true,
                                        'teeny' => false,
                                        'quicktags' => true,
                                        'textarea_rows' => 8,
                                ]
                        );
                        ?>
                    </div>
                </div>
            </div>
            <!-- ========== TAB 3: API URl ========== -->
            <div id="tab-api-url" class="wg-game-tab-content" style="display:none;">
                <h2><?php _e('URL API', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="api_base_url"><?php _e('API Base URL', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td><input type="text" name="api_base_url" id="api_base_url" value="<?php echo esc_attr(get_option('game_bsc_api_base_url', '')); ?>" style="width: 50%;"></td>
                    </tr>
                    <tr>
                        <th><label for="gotit_environment"><?php _e('Môi trường Got It', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td>
                            <?php $gotit_env = get_option('game_bsc_gotit_environment', 'staging'); ?>
                            <select name="gotit_environment" id="gotit_environment">
                                <option value="staging" <?php selected($gotit_env, 'staging'); ?>>Staging</option>
                                <option value="production" <?php selected($gotit_env, 'production'); ?>>Production</option>
                            </select>
                            <p class="description">Staging: https://openapi-stg.gotit.vn/ | Production: https://openapi.gotit.vn/</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gotit_api_key"><?php _e('Got It API Key', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="gotit_api_key" id="gotit_api_key" value="<?php echo esc_attr(get_option('game_bsc_gotit_api_key', '')); ?>" style="width: 60%;" autocomplete="off">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gotit_webhook_secret"><?php _e('Got It Webhook Secret Key', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="gotit_webhook_secret" id="gotit_webhook_secret" value="<?php echo esc_attr(get_option('game_bsc_gotit_webhook_secret', '')); ?>" style="width: 60%;" autocomplete="off">
                        </td>
                    </tr>

                    <tr>
                        <th><label for="trading_server"><?php _e('BSC Trading Server URL', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="trading_server" id="trading_server" value="<?php echo esc_attr(get_option('game_bsc_trading_server', '')); ?>" style="width: 60%;">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ========== TAB: VOUCHER SETTINGS ========== -->
            <div id="tab-voucher" class="wg-game-tab-content" style="display:none;">
                <h2><?php _e('Cài đặt Voucher', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php _e('Ảnh banner mặc định khi đã đổi voucher', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td>
                            <?php 
                            $default_banner_id = get_option('game_bsc_default_redeemed_banner', ''); 
                            $default_banner_url = $default_banner_id ? wp_get_attachment_image_url($default_banner_id, 'medium') : '';
                            ?>
                            <div class="image-preview-wrapper" style="margin-bottom: 10px;">
                                <img id="default_redeemed_banner_preview" src="<?php echo esc_url($default_banner_url); ?>" style="max-width:300px; max-height:300px; display:<?php echo $default_banner_url ? 'block' : 'none'; ?>;" />
                            </div>
                            <input type="hidden" name="game_bsc_default_redeemed_banner" id="default_redeemed_banner_id" value="<?php echo esc_attr($default_banner_id); ?>">
                            <button type="button" class="button" id="upload_default_redeemed_banner_button"><?php _e('Chọn ảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                            <button type="button" class="button" id="remove_default_redeemed_banner_button" style="display:<?php echo $default_banner_url ? 'inline-block' : 'none'; ?>;"><?php _e('Xóa ảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                            <p class="description">Ảnh này sẽ được dùng tự động làm ảnh banner đã đổi nếu voucher cụ thể không thiết lập ảnh banner riêng.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Logo brand mặc định', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td>
                            <?php 
                            $default_brand_logo_id = get_option('game_bsc_default_brand_logo', ''); 
                            $default_brand_logo_url = $default_brand_logo_id ? wp_get_attachment_image_url($default_brand_logo_id, 'medium') : '';
                            ?>
                            <div class="image-preview-wrapper" style="margin-bottom: 10px;">
                                <img id="default_brand_logo_preview" src="<?php echo esc_url($default_brand_logo_url); ?>" style="max-width:150px; max-height:150px; display:<?php echo $default_brand_logo_url ? 'block' : 'none'; ?>;" />
                            </div>
                            <input type="hidden" name="game_bsc_default_brand_logo" id="default_brand_logo_id" value="<?php echo esc_attr($default_brand_logo_id); ?>">
                            <button type="button" class="button" id="upload_default_brand_logo_button"><?php _e('Chọn ảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                            <button type="button" class="button" id="remove_default_brand_logo_button" style="display:<?php echo $default_brand_logo_url ? 'inline-block' : 'none'; ?>;"><?php _e('Xóa ảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                            <p class="description">Logo brand mặc định cho voucher (Got It). Nếu không thiết lập, hệ thống sẽ lấy logo từ thông tin voucher cụ thể hoặc từ Got It API.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ========== TAB: BANNER MANAGEMENT ========== -->
            <div id="tab-banners" class="wg-game-tab-content" style="display:none;">
                <h2><?php _e('Quản lí banner', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php _e('Ảnh banner hiển thị', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td>
                            <?php
                            $banner_id = get_option('game_bsc_banner_manager', '');
                            $banner_url = $banner_id ? wp_get_attachment_image_url($banner_id, 'medium') : '';
                            ?>
                            <div class="image-preview-wrapper" style="margin-bottom: 10px;">
                                <img id="banner_manager_preview" src="<?php echo esc_url($banner_url); ?>" style="max-width:300px; max-height:300px; display:<?php echo $banner_url ? 'block' : 'none'; ?>;" />
                            </div>
                            <input type="hidden" name="game_bsc_banner_manager" id="banner_manager_id" value="<?php echo esc_attr($banner_id); ?>">
                            <button type="button" class="button" id="upload_banner_manager_button"><?php _e('Chọn ảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                            <button type="button" class="button" id="remove_banner_manager_button" style="display:<?php echo $banner_url ? 'inline-block' : 'none'; ?>;"><?php _e('Xóa ảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                            <p class="description"><?php _e('Chọn ảnh banner cho game BSC. Ảnh này sẽ được trả về thông qua API.', WG_GAME_PLUGIN_TEXTDOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Banner mobile', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td>
                            <?php
                            $banner_mobile_id = get_option('game_bsc_banner_mobile', '');
                            $banner_mobile_url = $banner_mobile_id ? wp_get_attachment_image_url($banner_mobile_id, 'medium') : '';
                            ?>
                            <div class="image-preview-wrapper" style="margin-bottom: 10px;">
                                <img id="banner_mobile_preview" src="<?php echo esc_url($banner_mobile_url); ?>" style="max-width:300px; max-height:300px; display:<?php echo $banner_mobile_url ? 'block' : 'none'; ?>;" />
                            </div>
                            <input type="hidden" name="game_bsc_banner_mobile" id="banner_mobile_id" value="<?php echo esc_attr($banner_mobile_id); ?>">
                            <button type="button" class="button" id="upload_banner_mobile_button"><?php _e('Chọn ảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                            <button type="button" class="button" id="remove_banner_mobile_button" style="display:<?php echo $banner_mobile_url ? 'inline-block' : 'none'; ?>;"><?php _e('Xóa ảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                            <p class="description"><?php _e('Chọn ảnh banner cho phiên bản di động.', WG_GAME_PLUGIN_TEXTDOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Text', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td>
                            <?php
                            $banner_text = get_option('game_bsc_banner_text', '');
                            ?>
                            <input type="text" name="game_bsc_banner_text" id="banner_text" value="<?php echo esc_attr($banner_text); ?>" style="width: 50%;">
                            <p class="description"><?php _e('Nhập text hiển thị cho banner.', WG_GAME_PLUGIN_TEXTDOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Icon', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td>
                            <?php
                            $banner_icon_id = get_option('game_bsc_banner_icon', '');
                            $banner_icon_url = $banner_icon_id ? wp_get_attachment_image_url($banner_icon_id, 'medium') : '';
                            ?>
                            <div class="image-preview-wrapper" style="margin-bottom: 10px;">
                                <img id="banner_icon_preview" src="<?php echo esc_url($banner_icon_url); ?>" style="max-width:150px; max-height:150px; display:<?php echo $banner_icon_url ? 'block' : 'none'; ?>;" />
                            </div>
                            <input type="hidden" name="game_bsc_banner_icon" id="banner_icon_id" value="<?php echo esc_attr($banner_icon_id); ?>">
                            <button type="button" class="button" id="upload_banner_icon_button"><?php _e('Chọn ảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                            <button type="button" class="button" id="remove_banner_icon_button" style="display:<?php echo $banner_icon_url ? 'inline-block' : 'none'; ?>;"><?php _e('Xóa ảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                            <p class="description"><?php _e('Chọn icon cho banner.', WG_GAME_PLUGIN_TEXTDOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Icon mobile', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td>
                            <?php
                            $banner_icon_mobile_id = get_option('game_bsc_banner_icon_mobile', '');
                            $banner_icon_mobile_url = $banner_icon_mobile_id ? wp_get_attachment_image_url($banner_icon_mobile_id, 'medium') : '';
                            ?>
                            <div class="image-preview-wrapper" style="margin-bottom: 10px;">
                                <img id="banner_icon_mobile_preview" src="<?php echo esc_url($banner_icon_mobile_url); ?>" style="max-width:150px; max-height:150px; display:<?php echo $banner_icon_mobile_url ? 'block' : 'none'; ?>;" />
                            </div>
                            <input type="hidden" name="game_bsc_banner_icon_mobile" id="banner_icon_mobile_id" value="<?php echo esc_attr($banner_icon_mobile_id); ?>">
                            <button type="button" class="button" id="upload_banner_icon_mobile_button"><?php _e('Chọn ảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                            <button type="button" class="button" id="remove_banner_icon_mobile_button" style="display:<?php echo $banner_icon_mobile_url ? 'inline-block' : 'none'; ?>;"><?php _e('Xóa ảnh', WG_GAME_PLUGIN_TEXTDOMAIN); ?></button>
                            <p class="description"><?php _e('Chọn icon cho banner trên phiên bản di động.', WG_GAME_PLUGIN_TEXTDOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ========== TAB: TERMS & CONDITIONS ========== -->
            <div id="tab-terms" class="wg-game-tab-content" style="display:none;">
                <h2><?php _e('Điều khoản đổi quà', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h2>
                <p><?php _e('Quản lý các điều khoản và điều kiện khi đổi voucher Gotit', WG_GAME_PLUGIN_TEXTDOMAIN); ?></p>
                
                <table class="form-table" style="margin-bottom: 20px;">
                    <tr>
                        <th><label for="game_bsc_terms_link"><?php _e('Link điều khoản chung', WG_GAME_PLUGIN_TEXTDOMAIN); ?></label></th>
                        <td>
                            <?php
                            $terms_link = get_option('game_bsc_terms_link', '');
                            ?>
                            <input type="url" name="game_bsc_terms_link" id="game_bsc_terms_link" value="<?php echo esc_url($terms_link); ?>" style="width: 50%;" placeholder="https://example.com/terms">
                            <p class="description"><?php _e('Đường dẫn chi tiết đến trang điều khoản và điều kiện bên ngoài (nếu có).', WG_GAME_PLUGIN_TEXTDOMAIN); ?></p>
                        </td>
                    </tr>
                </table>

                <div id="terms-repeater">
                    <?php
                    if (empty($terms)) {
                        $terms = [['title' => '', 'content' => '']];
                    }
                    foreach ($terms as $index => $term):
                        ?>
                        <fieldset class="term-item" style="border:2px solid #0073aa; margin-bottom:15px; padding:15px; background:#f9f9f9; border-radius:5px;">
                            <legend style="padding:0 10px; color:#0073aa; font-weight:bold;">
                                <?php printf(__('Mục điều khoản #%d', WG_GAME_PLUGIN_TEXTDOMAIN), $index + 1); ?>
                            </legend>

                            <div class="form-group" style="margin-bottom:15px;">
                                <label for="term_title_<?php echo $index; ?>" style="display:block; margin-bottom:5px; font-weight:bold;">
                                    <?php _e('Tên điều khoản', WG_GAME_PLUGIN_TEXTDOMAIN); ?> <span style="color:red;">*</span>
                                </label>
                                <input
                                        type="text"
                                        id="term_title_<?php echo $index; ?>"
                                        name="terms[<?php echo $index; ?>][title]"
                                        value="<?php echo esc_attr($term['title'] ?? ''); ?>"
                                        class="regular-text"
                                        style="width:50%;"
                                        placeholder="<?php _e('Nhập tên điều khoản', WG_GAME_PLUGIN_TEXTDOMAIN); ?>"
                                >
                            </div>

                            <div class="form-group" style="margin-bottom:15px;">
                                <label for="term_content_<?php echo $index; ?>" style="display:block; margin-bottom:5px; font-weight:bold;">
                                    <?php _e('Nội dung điều khoản', WG_GAME_PLUGIN_TEXTDOMAIN); ?> <span style="color:red;">*</span>
                                </label>
                                <?php
                                wp_editor(
                                        isset($term['content']) ? stripslashes($term['content']) : '',
                                        'term_content_' . $index,
                                        [
                                                'textarea_name' => 'terms[' . $index . '][content]',
                                                'media_buttons' => false,
                                                'teeny' => true,
                                                'quicktags' => true,
                                        ]
                                );
                                ?>
                            </div>

                            <button type="button" class="button button-danger remove-term" style="background:#dc3545; color:white; border-color:#dc3545; cursor:pointer; padding:5px 10px;">
                                <?php _e('Xóa điều khoản này', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                            </button>
                        </fieldset>
                    <?php endforeach; ?>
                </div>

                <p>
                    <button type="button" id="add-term" class="button button-secondary" style="margin-bottom:20px;">
                        <?php _e('+ Thêm điều khoản', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
                    </button>
                </p>
            </div>

            <!-- SUBMIT BUTTON -->
            <p style="margin-top:30px; padding-top:20px; border-top:1px solid #ccc;">
                <input type="submit" name="save_settings" class="button-primary" value="<?php _e('Lưu cài đặt', WG_GAME_PLUGIN_TEXTDOMAIN); ?>">
            </p>
        </form>
    </div>

    <style>
        .wg-game-tabs {
            display: flex;
            gap: 0;
        }

        .wg-game-tabs .nav-tab {
            background: #f1f1f1;
            border: 1px solid #ccc;
            border-bottom: none;
            padding: 10px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 2px;
        }

        .wg-game-tabs .nav-tab:hover {
            background: #e5e5e5;
        }

        .wg-game-tabs .nav-tab-active {
            background: #fff;
            border-bottom: 3px solid #0073aa;
            color: #0073aa;
            font-weight: bold;
        }

        .wg-game-tab-content {
            padding: 20px;
            background: #fff;
            border: 1px solid #ccc;
            border-top: none;
        }

        .wg-game-tab-content h2 {
            margin-top: 0;
            color: #0073aa;
            padding-bottom: 10px;
            border-bottom: 2px solid #0073aa;
        }

        /* Mission Management Table Styles */
        table.widefat tbody tr:hover {
            background-color: #f5f9fc !important;
        }

        table.widefat tbody tr:hover td {
            background-color: transparent;
        }

        .guide-step-item:hover {
            border-color: #0073aa !important;
            box-shadow: 0 1px 3px rgba(0, 115, 170, 0.1);
        }

        .remove-guide-step:hover {
            background: #c82333 !important;
            transform: scale(1.05);
        }

        /* Scrollbar styling for guide section */
        .guide-steps-repeater::-webkit-scrollbar {
            width: 8px;
        }

        .guide-steps-repeater::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .guide-steps-repeater::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .guide-steps-repeater::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .rule-item {
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .rule-item legend {
            color: #0073aa;
            font-weight: bold;
        }
    </style>

    <script>
        // ========== MEDIA UPLOADER FOR VOUCHER BANNER (jQuery) ==========
        jQuery(document).ready(function($) {
            // Utility function to get attachment URL safely (prioritize smaller sizes for instant preview)
            function getAttachmentUrl(attachment) {
                if (attachment.sizes) {
                    if (attachment.sizes.medium && attachment.sizes.medium.url) return attachment.sizes.medium.url;
                    if (attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) return attachment.sizes.thumbnail.url;
                    if (attachment.sizes.full && attachment.sizes.full.url) return attachment.sizes.full.url;
                }
                return attachment.url || '';
            }

            var banner_frame;
            $('#upload_default_redeemed_banner_button').on('click', function(e) {
                e.preventDefault();
                if (banner_frame) {
                    banner_frame.open();
                    return;
                }
                banner_frame = wp.media({
                    title: 'Chon anh banner mac dinh',
                    button: { text: 'Su dung anh nay' },
                    multiple: false
                });
                banner_frame.on('select', function() {
                    var attachment = banner_frame.state().get('selection').first().toJSON();
                    var imageUrl = getAttachmentUrl(attachment);
                    $('#default_redeemed_banner_id').val(attachment.id);
                    if (imageUrl) {
                        $('#default_redeemed_banner_preview').attr('src', imageUrl).css('display', 'block').show();
                    }
                    $('#remove_default_redeemed_banner_button').css('display', 'inline-block').show();
                });
                banner_frame.open();
            });

            $('#remove_default_redeemed_banner_button').on('click', function(e) {
                e.preventDefault();
                $('#default_redeemed_banner_id').val('');
                $('#default_redeemed_banner_preview').attr('src', '').hide();
                $(this).hide();
            });

            // ========== MEDIA UPLOADER FOR BRAND LOGO ==========
            var brand_logo_frame;
            $('#upload_default_brand_logo_button').on('click', function(e) {
                e.preventDefault();
                if (brand_logo_frame) {
                    brand_logo_frame.open();
                    return;
                }
                brand_logo_frame = wp.media({
                    title: 'Chon anh logo brand',
                    button: { text: 'Su dung anh nay' },
                    multiple: false
                });
                brand_logo_frame.on('select', function() {
                    var attachment = brand_logo_frame.state().get('selection').first().toJSON();
                    var imageUrl = getAttachmentUrl(attachment);
                    $('#default_brand_logo_id').val(attachment.id);
                    if (imageUrl) {
                        $('#default_brand_logo_preview').attr('src', imageUrl).css('display', 'block').show();
                    }
                    $('#remove_default_brand_logo_button').css('display', 'inline-block').show();
                });
                brand_logo_frame.open();
            });

            $('#remove_default_brand_logo_button').on('click', function(e) {
                e.preventDefault();
                $('#default_brand_logo_id').val('');
                $('#default_brand_logo_preview').attr('src', '').hide();
                $(this).hide();
            });

            // ========== MEDIA UPLOADER FOR BANNER MANAGER ==========
            var banner_manager_frame;
            $('#upload_banner_manager_button').on('click', function(e) {
                e.preventDefault();
                if (banner_manager_frame) {
                    banner_manager_frame.open();
                    return;
                }
                banner_manager_frame = wp.media({
                    title: 'Chọn ảnh banner',
                    button: { text: 'Sử dụng ảnh này' },
                    multiple: false
                });
                banner_manager_frame.on('select', function() {
                    var attachment = banner_manager_frame.state().get('selection').first().toJSON();
                    var imageUrl = getAttachmentUrl(attachment);
                    $('#banner_manager_id').val(attachment.id);
                    if (imageUrl) {
                        $('#banner_manager_preview').attr('src', imageUrl).css('display', 'block').show();
                    }
                    $('#remove_banner_manager_button').css('display', 'inline-block').show();
                });
                banner_manager_frame.open();
            });

            $('#remove_banner_manager_button').on('click', function(e) {
                e.preventDefault();
                $('#banner_manager_id').val('');
                $('#banner_manager_preview').attr('src', '').hide();
                $(this).hide();
            });

            // ========== MEDIA UPLOADER FOR BANNER MOBILE ==========
            var banner_mobile_frame;
            $('#upload_banner_mobile_button').on('click', function(e) {
                e.preventDefault();
                if (banner_mobile_frame) {
                    banner_mobile_frame.open();
                    return;
                }
                banner_mobile_frame = wp.media({
                    title: 'Chọn ảnh banner mobile',
                    button: { text: 'Sử dụng ảnh này' },
                    multiple: false
                });
                banner_mobile_frame.on('select', function() {
                    var attachment = banner_mobile_frame.state().get('selection').first().toJSON();
                    var imageUrl = getAttachmentUrl(attachment);
                    $('#banner_mobile_id').val(attachment.id);
                    if (imageUrl) {
                        $('#banner_mobile_preview').attr('src', imageUrl).css('display', 'block').show();
                    }
                    $('#remove_banner_mobile_button').css('display', 'inline-block').show();
                });
                banner_mobile_frame.open();
            });

            $('#remove_banner_mobile_button').on('click', function(e) {
                e.preventDefault();
                $('#banner_mobile_id').val('');
                $('#banner_mobile_preview').attr('src', '').hide();
                $(this).hide();
            });

            // ========== MEDIA UPLOADER FOR BANNER ICON ==========
            var banner_icon_frame;
            $('#upload_banner_icon_button').on('click', function(e) {
                e.preventDefault();
                if (banner_icon_frame) {
                    banner_icon_frame.open();
                    return;
                }
                banner_icon_frame = wp.media({
                    title: 'Chọn icon banner',
                    button: { text: 'Sử dụng ảnh này' },
                    multiple: false
                });
                banner_icon_frame.on('select', function() {
                    var attachment = banner_icon_frame.state().get('selection').first().toJSON();
                    var imageUrl = getAttachmentUrl(attachment);
                    $('#banner_icon_id').val(attachment.id);
                    if (imageUrl) {
                        $('#banner_icon_preview').attr('src', imageUrl).css('display', 'block').show();
                    }
                    $('#remove_banner_icon_button').css('display', 'inline-block').show();
                });
                banner_icon_frame.open();
            });

            $('#remove_banner_icon_button').on('click', function(e) {
                e.preventDefault();
                $('#banner_icon_id').val('');
                $('#banner_icon_preview').attr('src', '').hide();
                $(this).hide();
            });

            // ========== MEDIA UPLOADER FOR BANNER ICON MOBILE ==========
            var banner_icon_mobile_frame;
            $('#upload_banner_icon_mobile_button').on('click', function(e) {
                e.preventDefault();
                if (banner_icon_mobile_frame) {
                    banner_icon_mobile_frame.open();
                    return;
                }
                banner_icon_mobile_frame = wp.media({
                    title: 'Chọn icon banner mobile',
                    button: { text: 'Sử dụng ảnh này' },
                    multiple: false
                });
                banner_icon_mobile_frame.on('select', function() {
                    var attachment = banner_icon_mobile_frame.state().get('selection').first().toJSON();
                    var imageUrl = getAttachmentUrl(attachment);
                    $('#banner_icon_mobile_id').val(attachment.id);
                    if (imageUrl) {
                        $('#banner_icon_mobile_preview').attr('src', imageUrl).css('display', 'block').show();
                    }
                    $('#remove_banner_icon_mobile_button').css('display', 'inline-block').show();
                });
                banner_icon_mobile_frame.open();
            });

            $('#remove_banner_icon_mobile_button').on('click', function(e) {
                e.preventDefault();
                $('#banner_icon_mobile_id').val('');
                $('#banner_icon_mobile_preview').attr('src', '').hide();
                $(this).hide();
            });
        });

        // ========== TAB SWITCHING (Vanilla JS) ==========
        document.addEventListener('DOMContentLoaded', function() {
            var tabLinks = document.querySelectorAll('.wg-game-tabs .nav-tab');
            var tabContents = document.querySelectorAll('.wg-game-tab-content');

            tabLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var tabName = this.getAttribute('data-tab');
                    var tabId = 'tab-' + tabName;

                    tabLinks.forEach(function(t) { t.classList.remove('nav-tab-active'); });
                    tabContents.forEach(function(tc) { tc.style.display = 'none'; });

                    this.classList.add('nav-tab-active');
                    var activeTab = document.getElementById(tabId);
                    if (activeTab) {
                        activeTab.style.display = 'block';
                    }

                    localStorage.setItem('game_bsc_active_tab', tabName);
                });
            });

            // Restore tab tu localStorage
            var savedTab = localStorage.getItem('game_bsc_active_tab') || 'general';
            var savedTabLink = document.querySelector('.wg-game-tabs .nav-tab[data-tab="' + savedTab + '"]');
            if (savedTabLink) {
                savedTabLink.click();
            }

            // ========== QUẢN LÝ STAGES ==========
            const stagesDiv = document.getElementById('stages');
            const addBtn = document.getElementById('add-stage');

            addBtn.addEventListener('click', function() {
                const index = stagesDiv.querySelectorAll('.stage-item').length;
                const html = `
                <fieldset class="stage-item" style="border:1px solid #ccc; margin-bottom:10px; padding:10px;">
                    <legend>Chặng ${index + 1}</legend>
                    <!-- Hidden: Từ ngày field -->
                    <!-- <label>Từ ngày <input type="date" name="stages[${index}][from]"></label> -->
                    <!-- Hidden: Đến ngày field -->
                    <!-- <label>Đến ngày <input type="date" name="stages[${index}][to]"></label> -->
                     <label>Từ chặng <input type="number" style="width: 5%" name="stages[${index}][from_stage]"></label>
                        <label>Đến chặng <input type="number" style="width: 5%" name="stages[${index}][to_stage]"></label>
                    <!-- Hidden: Thời gian trả lời field -->
                    <!-- <label>Thời gian trả lời 1 câu hỏi (giây) <input type="number" name="stages[${index}][duration]"></label> -->
                    <label>Điểm tặng mỗi câu <input type="number" name="stages[${index}][score]"></label>
                    <label>Số câu hỏi/ngày <input type="number" name="stages[${index}][questions_per_day]"></label>
                    <button type="button" class="button remove-stage">Xóa chặng</button>
                </fieldset>
                `;
                stagesDiv.insertAdjacentHTML('beforeend', html);
            });

            stagesDiv.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-stage')) {
                    e.target.closest('.stage-item').remove();
                    stagesDiv.querySelectorAll('.stage-item').forEach(function(item, idx) {
                        item.querySelector('legend').textContent = 'Chặng ' + (idx + 1);
                        item.querySelectorAll('input').forEach(function(input) {
                            const name = input.name.replace(/stages\[\d+\]/, `stages[${idx}]`);
                            input.name = name;
                        });
                    });
                }
            });

            // ========== HELPER FUNCTION: Init WP Editor ==========
            function game_bsc_init_editor(editorId) {
                if (typeof tinymce !== 'undefined') {
                    // Nếu editor đã được init, loại bỏ nó trước khi init lại
                    if (tinymce.get(editorId)) {
                        tinymce.get(editorId).remove();
                    }
                    // Nếu TinyMCE đã được load
                    tinymce.init({
                        selector: '#' + editorId,
                        menubar: false,
                        statusbar: true,
                        height: 200,
                        // WordPress default plugins (include media)
                        plugins: 'charmap,colorpicker,hr,lists,media,paste,tabfocus,textcolor,fullscreen,wordpress,wpautoresize,wpeditimage,wpemoji,wpgallery,wplink,wpdialogs,wptextpattern,wpview',
                        // Toolbar row 1 - main formatting tools with media button
                        toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,media,wp_more,spellchecker,fullscreen,wp_adv',
                        // Toolbar row 2 - advanced formatting (shown when wp_adv is clicked)
                        toolbar2: 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
                        // Block formats dropdown
                        block_formats: 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6;Preformatted=pre',
                        // Editor behavior
                        relative_urls: false,
                        remove_script_host: false,
                        convert_urls: false,
                        browser_spellcheck: true,
                        entity_encoding: 'raw',
                        keep_styles: false,
                        paste_webkit_styles: 'font-weight font-style color',
                        paste_remove_styles_if_webkit: true,
                        paste_strip_class_attributes: 'all',
                        paste_text_use_dialog: false,
                        wpeditimage_html5_captions: true,
                        end_container_on_empty_block: true,
                        // WordPress integration
                        wp_autoresize_on: true,
                        add_unload_trigger: true,
                        // Setup callback
                        setup: function(editor) {
                            editor.on('init', function() {
                                console.log('Editor initialized: ' + editorId);
                            });
                        }
                    });
                } else {
                    console.warn('TinyMCE not loaded for: ' + editorId);
                }
            }

            // ========== QUẢN LÝ GUIDE STEPS REPEATER ==========
            document.addEventListener('click', function(e) {
                // Thêm bước hướng dẫn
                if (e.target.classList.contains('add-guide-step')) {
                    const missionCode = e.target.getAttribute('data-mission-code');
                    const repeater = document.querySelector(`.guide-steps-repeater[data-mission-code="${missionCode}"]`);
                    const stepIndex = repeater.querySelectorAll('.guide-step-item').length;

                    const stepHtml = `
                        <div class="guide-step-item" style="border: 1px solid #d5d5d5; padding: 10px; margin-bottom: 8px; background: #f9f9f9; border-radius: 3px; border-left: 3px solid #0073aa;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong style="color: #0073aa; font-size: 12px; display: flex; align-items: center; gap: 5px;">
                                    <span style="background: #0073aa; color: #fff; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px;">
                                        ${stepIndex + 1}
                                    </span>
                                    Bước ${stepIndex + 1}
                                </strong>
                                <button type="button" class="button button-small remove-guide-step" style="background: #dc3545; color: white; border-color: #dc3545; padding: 3px 10px; height: auto; line-height: 1.4; font-size: 11px; border-radius: 3px; cursor: pointer;">
                                    ✕ Xóa
                                </button>
                            </div>
                            <textarea
                                id="task-guide-step-${missionCode}-${stepIndex}"
                                name="tasks[${missionCode}][guide_steps][${stepIndex}][content]"
                                style="width:100%; height:100px; padding:5px; border:1px solid #ddd;"
                                class="wp-editor-textarea"
                            ></textarea>
                        </div>
                    `;

                    repeater.insertAdjacentHTML('beforeend', stepHtml);

                    // Khởi tạo editor cho bước mới
                    game_bsc_init_editor(`task-guide-step-${missionCode}-${stepIndex}`);
                }

                // Xóa bước hướng dẫn
                if (e.target.classList.contains('remove-guide-step')) {
                    const stepItem = e.target.closest('.guide-step-item');
                    const repeater = e.target.closest('.guide-steps-repeater');

                    if (repeater.querySelectorAll('.guide-step-item').length > 1) {
                        stepItem.remove();

                        // Cập nhật lại số thứ tự
                        repeater.querySelectorAll('.guide-step-item').forEach(function(item, idx) {
                            const strong = item.querySelector('strong');
                            if (strong) {
                                const numberSpan = strong.querySelector('span');
                                if (numberSpan) {
                                    numberSpan.textContent = idx + 1;
                                }
                                // Update the text while keeping the span
                                const textNode = Array.from(strong.childNodes).find(node => node.nodeType === 3);
                                if (textNode) {
                                    textNode.textContent = 'Bước ' + (idx + 1);
                                }
                            }
                        });
                    } else {
                        alert('Phải có ít nhất một bước hướng dẫn');
                    }
                }
            });

            // ========== QUẢN LÝ RULES - FIX WYSIWYG EDITOR ==========
            const rulesDiv = document.getElementById('rules-repeater');
            const addRuleBtn = document.getElementById('add-rule');
            let ruleEditorIndex = rulesDiv.querySelectorAll('.rule-item').length;

            addRuleBtn.addEventListener('click', function() {
                const index = rulesDiv.querySelectorAll('.rule-item').length;

                // Thêm form trước
                const html = `
                <fieldset class="rule-item" style="border:2px solid #0073aa; margin-bottom:15px; padding:15px; background:#f9f9f9; border-radius:5px;">
                    <legend style="padding:0 10px; color:#0073aa; font-weight:bold;">
                        Thể lệ #${index + 1}
                    </legend>

                    <div class="form-group" style="margin-bottom:15px;">
                        <label for="rule_title_${index}" style="display:block; margin-bottom:5px; font-weight:bold;">
                            Tiêu đề thể lệ <span style="color:red;">*</span>
                        </label>
                        <input
                            type="text"
                            id="rule_title_${index}"
                            name="rules[${index}][title]"
                            value=""
                            class="regular-text"
                            placeholder="Nhập tiêu đề thể lệ"
                        >
                    </div>

                    <div class="form-group" style="margin-bottom:15px;">
                        <label for="rule_content_${index}" style="display:block; margin-bottom:5px; font-weight:bold;">
                            Nội dung thể lệ <span style="color:red;">*</span>
                        </label>
                        <textarea
                            id="rule_content_${index}"
                            name="rules[${index}][content]"
                            style="width:100%; height:200px; padding:10px; border:1px solid #ddd; font-family:Arial, sans-serif;"
                            class="wp-editor-textarea"
                        ></textarea>
                    </div>

                    <button type="button" class="button button-danger remove-rule" style="background:#dc3545; color:white; border-color:#dc3545; cursor:pointer; padding:5px 10px;">
                        Xóa thể lệ này
                    </button>
                </fieldset>
                `;
                rulesDiv.insertAdjacentHTML('beforeend', html);

                // AJAX call để khởi tạo WYSIWYG editor
                game_bsc_init_editor('rule_content_' + index);
            });

            rulesDiv.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-rule')) {
                    if (rulesDiv.querySelectorAll('.rule-item').length > 1) {
                        e.target.closest('.rule-item').remove();
                        // Cập nhật lại số thứ tự
                        rulesDiv.querySelectorAll('.rule-item').forEach(function(item, idx) {
                            item.querySelector('legend').textContent = 'Thể lệ #' + (idx + 1);
                            item.querySelectorAll('input, textarea').forEach(function(input) {
                                const name = input.name.replace(/rules\[\d+\]/, `rules[${idx}]`);
                                input.name = name;
                                const id = input.id.replace(/_\d+$/, `_${idx}`);
                                input.id = id;
                            });
                            item.querySelectorAll('label').forEach(function(label) {
                                const forAttr = label.getAttribute('for');
                                if (forAttr) {
                                    const newForAttr = forAttr.replace(/_\d+$/, `_${idx}`);
                                    label.setAttribute('for', newForAttr);
                                }
                            });
                        });
                    } else {
                        alert('Phải có ít nhất một thể lệ');
                    }
                }
            });

            // ========== QUẢN LÝ TERMS (ĐIỀU KHOẢN) - FIX WYSIWYG EDITOR ==========
            const termsDiv = document.getElementById('terms-repeater');
            const addTermBtn = document.getElementById('add-term');

            if (addTermBtn && termsDiv) {
                addTermBtn.addEventListener('click', function() {
                    const index = termsDiv.querySelectorAll('.term-item').length;

                    // Thêm form trước
                    const html = `
                    <fieldset class="term-item" style="border:2px solid #0073aa; margin-bottom:15px; padding:15px; background:#f9f9f9; border-radius:5px;">
                        <legend style="padding:0 10px; color:#0073aa; font-weight:bold;">
                            Mục điều khoản #${index + 1}
                        </legend>

                        <div class="form-group" style="margin-bottom:15px;">
                            <label for="term_title_${index}" style="display:block; margin-bottom:5px; font-weight:bold;">
                                Tên điều khoản <span style="color:red;">*</span>
                            </label>
                            <input
                                type="text"
                                id="term_title_${index}"
                                name="terms[${index}][title]"
                                value=""
                                class="regular-text"
                                style="width:50%;"
                                placeholder="Nhập tên điều khoản"
                            >
                        </div>

                        <div class="form-group" style="margin-bottom:15px;">
                            <label for="term_content_${index}" style="display:block; margin-bottom:5px; font-weight:bold;">
                                Nội dung điều khoản <span style="color:red;">*</span>
                            </label>
                            <textarea
                                id="term_content_${index}"
                                name="terms[${index}][content]"
                                style="width:100%; height:200px; padding:10px; border:1px solid #ddd; font-family:Arial, sans-serif;"
                                class="wp-editor-textarea"
                            ></textarea>
                        </div>

                        <button type="button" class="button button-danger remove-term" style="background:#dc3545; color:white; border-color:#dc3545; cursor:pointer; padding:5px 10px;">
                            Xóa điều khoản này
                        </button>
                    </fieldset>
                    `;
                    termsDiv.insertAdjacentHTML('beforeend', html);

                    // Khởi tạo WYSIWYG editor trực tiếp thông qua helper function cục bộ
                    game_bsc_init_editor('term_content_' + index);
                });

                termsDiv.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-term')) {
                        if (termsDiv.querySelectorAll('.term-item').length > 1) {
                            e.target.closest('.term-item').remove();
                            // Cập nhật lại số thứ tự
                            termsDiv.querySelectorAll('.term-item').forEach(function(item, idx) {
                                item.querySelector('legend').textContent = 'Mục điều khoản #' + (idx + 1);
                                item.querySelectorAll('input, textarea').forEach(function(input) {
                                    const name = input.name.replace(/terms\[\d+\]/, `terms[${idx}]`);
                                    input.name = name;
                                    const id = input.id.replace(/_\d+$/, `_${idx}`);
                                    input.id = id;
                                });
                                item.querySelectorAll('label').forEach(function(label) {
                                    const forAttr = label.getAttribute('for');
                                    if (forAttr) {
                                        const newForAttr = forAttr.replace(/_\d+$/, `_${idx}`);
                                        label.setAttribute('for', newForAttr);
                                    }
                                });
                            });
                        } else {
                            alert('Phải có ít nhất một mục điều khoản');
                        }
                    }
                });
            }
        });

        /**
         * AJAX function để khởi tạo WYSIWYG editor động
         */
        function game_bsc_init_editor(editorId) {
            if (typeof tinymce === 'undefined' || typeof wp === 'undefined') {
                console.warn('TinyMCE hoặc WordPress không được load');
                return;
            }

            // Nếu editor đã được init, loại bỏ nó
            if (tinymce.get(editorId)) {
                tinymce.get(editorId).remove();
            }

            // Gửi AJAX request để WordPress khởi tạo editor
            wp.ajax.post('game_bsc_init_rule_editor', {
                editor_id: editorId,
                nonce: '<?php echo wp_create_nonce('game_bsc_init_editor'); ?>'
            }).done(function(response) {
                // Response có chứa HTML editor đã render
                if (response && response.html) {
                    const textarea = document.getElementById(editorId);
                    if (textarea) {
                        textarea.parentNode.insertAdjacentHTML('afterend', response.html);
                    }
                }

                // Khởi tạo TinyMCE cho editor này
                tinymce.init({
                    selector: '#' + editorId,
                    menubar: false,
                    statusbar: true,
                    height: 200,
                    // WordPress default plugins (include media)
                    plugins: 'charmap,colorpicker,hr,lists,media,paste,tabfocus,textcolor,fullscreen,wordpress,wpautoresize,wpeditimage,wpemoji,wpgallery,wplink,wpdialogs,wptextpattern,wpview',
                    // Toolbar row 1 - main formatting tools with media button
                    toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,media,wp_more,spellchecker,fullscreen,wp_adv',
                    // Toolbar row 2 - advanced formatting (shown when wp_adv is clicked)
                    toolbar2: 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
                    // Block formats dropdown
                    block_formats: 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6;Preformatted=pre',
                    // Editor behavior
                    relative_urls: false,
                    remove_script_host: false,
                    convert_urls: false,
                    browser_spellcheck: true,
                    entity_encoding: 'raw',
                    keep_styles: false,
                    paste_webkit_styles: 'font-weight font-style color',
                    paste_remove_styles_if_webkit: true,
                    paste_strip_class_attributes: 'all',
                    paste_text_use_dialog: false,
                    wpeditimage_html5_captions: true,
                    end_container_on_empty_block: true,
                    // WordPress integration
                    wp_autoresize_on: true,
                    add_unload_trigger: true
                });
            }).fail(function(error) {
                console.error('Lỗi khởi tạo editor:', error);
            });
        }
    </script>
    <?php
}

/**
 * AJAX handler để khởi tạo editor
 */
add_action('wp_ajax_game_bsc_init_rule_editor', function() {
    check_ajax_referer('game_bsc_init_editor');

    $editor_id = sanitize_text_field($_POST['editor_id'] ?? '');

    if (empty($editor_id)) {
        wp_send_json_error('Missing editor_id');
    }

    // Lưu ý: wp_editor không thể được gọi qua AJAX đơn giản
    // Thay vào đó, ta sẽ khởi tạo TinyMCE động trên client
    wp_send_json_success(['html' => '']);
});

/**
 * Handle form submission
 */
add_action('admin_post_game_bsc_save_settings', 'game_bsc_handle_save_settings');

function game_bsc_handle_save_settings() {
    $admin_url = admin_url('admin.php?page=game-bsc-settings');
    if (!current_user_can('admin_game') && !current_user_can('administrator')) {
        wp_die(__('Bạn không có quyền thực hiện hành động này.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }
    check_admin_referer('game_bsc_save_settings');

    // ===== LẤY GIÁTRỊ CŨ ĐỂ LOG =====
    $old_start_date = get_option('game_bsc_start_date');
    $old_end_date = get_option('game_bsc_end_date');

    $old_daily_start_time = get_option('game_bsc_daily_start_time');
    $old_daily_end_time = get_option('game_bsc_daily_end_time');
    $old_stages = get_option('game_bsc_stages', []);
    $old_max_wrong_answers = get_option('game_bsc_max_wrong_answers', 0);
    $old_tasks = get_option('game_bsc_tasks', []);
    $old_rules = get_option('game_bsc_rules', []);
    $old_rewards = get_option('game_bsc_rewards_descriptions', []);
    $old_terms = get_option('game_bsc_terms', []);
    $old_terms_link = get_option('game_bsc_terms_link', '');
    $old_api_base_url = get_option('game_bsc_api_base_url', '');
    $old_gotit_environment = get_option('game_bsc_gotit_environment', 'staging');
    $old_gotit_api_key = get_option('game_bsc_gotit_api_key', '');
    $old_gotit_webhook_secret = get_option('game_bsc_gotit_webhook_secret', '');

    $old_max_drop_pieces = get_option('game_bsc_max_drop_pieces_per_day', 0);
    $old_price_drop_rate = get_option('game_bsc_piece_drop_rate', 0);
    $old_day_allowed_to_play_game_after_period_ends = get_option('game_bsc_day_allowed_to_play_game_after_period_ends', 0);

    // Save dates
    $start_date = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date = sanitize_text_field($_POST['end_date'] ?? '');
    $daily_start_time = sanitize_text_field($_POST['daily_start_time'] ?? '00:00');
    $daily_end_time = sanitize_text_field($_POST['daily_end_time'] ?? '23:59');

    $stages = $_POST['stages'] ?? [];
    $max_wrong_answers = intval($_POST['max_wrong_answers'] ?? 0);
    $max_drop_pieces = intval($_POST['max_drop_pieces_per_day'] ?? 0);
    $max_user_drop_pieces = intval($_POST['max_user_drop_pieces_per_day'] ?? 3);
    $piece_drop_rate = intval($_POST['piece_drop_rate'] ?? 0);
    $day_allowed_to_play_game_after_period_ends = intval($_POST['day_allowed_to_play_game_after_period_ends'] ?? 0);
    // Validate dates
    if(empty($start_date) || empty($end_date)) {
        return game_bsc_redirect_error(__('Phải nhập ngày bắt đầu và ngày kết thúc.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }
    if (!strtotime($start_date) || !strtotime($end_date)) {
        return game_bsc_redirect_error(__('Ngày không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }
    if ($start_date > $end_date) {
        return game_bsc_redirect_error(__('Ngày bắt đầu phải trước ngày kết thúc.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }

    if (strtotime($daily_start_time) === false || strtotime($daily_end_time) === false) {
        return game_bsc_redirect_error(__('Thời gian trong ngày không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }
    if ($daily_start_time >= $daily_end_time) {
        return game_bsc_redirect_error(__('Thời gian bắt đầu trong ngày phải trước thời gian kết thúc.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }



    update_option('game_bsc_start_date', $start_date);
    update_option('game_bsc_end_date', $end_date);

    update_option('game_bsc_daily_start_time', $daily_start_time);
    update_option('game_bsc_daily_end_time', $daily_end_time);

    // Cập nhật số lần trả lời sai tối đa
    if($max_wrong_answers < 0) $max_wrong_answers = 0;
    update_option('game_bsc_max_wrong_answers', $max_wrong_answers);

    // Cập nhật số mảnh rơi tối đa / ngày của hệ thống
    if($max_drop_pieces < 0) $max_drop_pieces = 0;
    update_option('game_bsc_max_drop_pieces_per_day', $max_drop_pieces);

    // Cập nhật số mảnh rơi tối đa / ngày của người chơi
    if($max_user_drop_pieces <= 0) $max_user_drop_pieces = 3;
    update_option('game_bsc_max_user_drop_pieces_per_day', $max_user_drop_pieces);

    // cập nhật tỉ lệ rơi mảnh , mặc định 30%
    if($piece_drop_rate < 0) $piece_drop_rate = 30;
    update_option('game_bsc_piece_drop_rate', $piece_drop_rate);

    // Cập nhật số ngày được chơi sau khi kết thúc kỳ
    update_option('game_bsc_day_allowed_to_play_game_after_period_ends', $day_allowed_to_play_game_after_period_ends);

    // Validate stages
    if(empty($stages)) {
        return game_bsc_redirect_error(__('Phải có ít nhất một chặng.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }

    $prev_to = null;
    $new_stages = [];
    foreach ($stages as $i => $stage) {
//        $from = sanitize_text_field($stage['from'] ?? '');
//        $to = sanitize_text_field($stage['to'] ?? '');
        $from_stage = intval($stage['from_stage'] ?? 0);
        $to_stage = intval($stage['to_stage'] ?? 0);
//        $duration = intval($stage['duration'] ?? 0);
        $score = intval($stage['score'] ?? 0);
        $questions_per_day = intval($stage['questions_per_day'] ?? 0);

        if( empty($score) || empty($questions_per_day)) {
            return game_bsc_redirect_error(sprintf(__('Phải nhập đầy đủ thông tin cho chặng %d.', WG_GAME_PLUGIN_TEXTDOMAIN), $i + 1), $admin_url);
        }

//        if (!strtotime($from) || !strtotime($to)) {
//            return game_bsc_redirect_error(sprintf(__('Ngày không hợp lệ ở chặng %d.', WG_GAME_PLUGIN_TEXTDOMAIN), $i + 1), $admin_url);
//        }
//
//        if ($from > $to) {
//            return game_bsc_redirect_error(sprintf(__('Trong chặng %d, ngày bắt đầu phải trước ngày kết thúc.', WG_GAME_PLUGIN_TEXTDOMAIN), $i + 1), $admin_url);
//        }
//
//        if ($from < $start_date || $to > $end_date) {
//            return game_bsc_redirect_error(sprintf(__('Chặng %d phải nằm trong khoảng thời gian của game.', WG_GAME_PLUGIN_TEXTDOMAIN), $i + 1), $admin_url);
//        }

        if ($from_stage <= 0 || $to_stage <= 0 || $from_stage > $to_stage) {
            return game_bsc_redirect_error(sprintf(__('Trong chặng %d, từ chặng và đến chặng phải lớn hơn 0 và từ chặng phải nhỏ hơn hoặc bằng đến chặng.', WG_GAME_PLUGIN_TEXTDOMAIN), $i + 1), $admin_url);
        }

        if ($i > 0) {
            $prev_stage = $stages[$i - 1];
            $prev_to_stage = intval($prev_stage['to_stage'] ?? 0);
            if ($from_stage <= $prev_to_stage) {
                return game_bsc_redirect_error(sprintf(__('Trong chặng %d, từ chặng phải lớn hơn đến chặng của chặng trước.', WG_GAME_PLUGIN_TEXTDOMAIN), $i + 1), $admin_url);
            }
        }


//        if ($prev_to !== null && $from <= $prev_to) {
//            return game_bsc_redirect_error(sprintf(__('Ngày bắt đầu của chặng %d phải sau ngày kết thúc của chặng trước.', WG_GAME_PLUGIN_TEXTDOMAIN), $i + 1), $admin_url);
//        }
//
//        if ($duration <= 0 || $score <= 0 || $questions_per_day <= 0) {
//            return game_bsc_redirect_error(sprintf(__('Trong chặng %d, thời gian trả lời, điểm tặng và số câu hỏi/ngày phải lớn hơn 0.', WG_GAME_PLUGIN_TEXTDOMAIN), $i + 1), $admin_url);
//        }

        $new_stages[$i] = [
//                'from' => $from,
//                'to' => $to,
                'from_stage' => $from_stage,
                'to_stage' => $to_stage,
//                'duration' => $duration,
                'score' => $score,
                'questions_per_day' => $questions_per_day,
        ];
//        $prev_to = $to;
    }

    update_option('game_bsc_stages', $new_stages);

    // Save tasks
    $mission_codes = include GAME_BSC_PLUGIN_DIR . 'config/missions.php';
    $tasks = $_POST['tasks'] ?? [];
    $new_tasks = [];

    foreach ($mission_codes as $mission) {
        $title = sanitize_text_field($tasks[$mission['code']]['title'] ?? '');
        $reward_spins = intval($tasks[$mission['code']]['reward_spins'] ?? 0);
        $api_url = sanitize_text_field($tasks[$mission['code']]['api_url'] ?? '');
//        $description = wp_kses_post($tasks[$mission['code']]['description'] ?? '');
        $amount_required = 0;
        if ($mission['code'] === TRADE_100M_VND_CODE || $mission['code'] === FIRST_DEPOSIT_CODE) {
            $amount_required = intval($tasks[$mission['code']]['amount_required'] ?? 0);
        }

        // Lấy các field mới cho hướng dẫn nhiệm vụ
        $guide_note = wp_kses_post($tasks[$mission['code']]['guide_note'] ?? '');
        $guide_delay = sanitize_text_field($tasks[$mission['code']]['guide_delay'] ?? '');
        $guide_steps = [];
        if (isset($tasks[$mission['code']]['guide_steps']) && is_array($tasks[$mission['code']]['guide_steps'])) {
            foreach ($tasks[$mission['code']]['guide_steps'] as $step) {
                if (!empty($step['content'])) {
                    $guide_steps[] = [
                        'content' => wp_kses_post($step['content'])
                    ];
                }
            }
        }

        if ($title !== '') {
            $new_tasks[$mission['code']] = [
                    'title' => $title,
                    'reward_spins' => $reward_spins,
                    'api_url' => $api_url,
//                    'description' => $description,
                    'guide_note' => $guide_note,
                    'guide_delay' => $guide_delay,
                    'guide_steps' => $guide_steps,
            ];
        }
        if ($mission['code'] === TRADE_100M_VND_CODE && $amount_required > 0 || $mission['code'] === FIRST_DEPOSIT_CODE) {
            $new_tasks[$mission['code']]['amount_required'] = $amount_required;
        }
    }
    update_option('game_bsc_tasks', $new_tasks, false);

    // Save rules
    if (isset($_POST['rules']) && is_array($_POST['rules'])) {
        $rules = array_map(function($rule) {
            return [
                    'title' => sanitize_text_field($rule['title'] ?? ''),
                    'content' => wp_kses_post($rule['content'] ?? ''),
            ];
        }, $_POST['rules']);

        $rules = array_filter($rules, function($rule) {
            return !empty($rule['title']) || !empty($rule['content']);
        });

        update_option('game_bsc_rules', array_values($rules));
    }

    // Save rewards descriptions
    if (isset($_POST['rewards_descriptions']) && is_array($_POST['rewards_descriptions'])) {
        $rewards = [];
        foreach ($_POST['rewards_descriptions'] as $key => $content) {
            $rewards[$key] = wp_kses_post($content);
        }
        update_option('game_bsc_rewards_descriptions', $rewards);
    }

    // Save terms & conditions
    if (isset($_POST['terms']) && is_array($_POST['terms'])) {
        $terms = array_map(function($term) {
            return [
                    'title' => sanitize_text_field($term['title'] ?? ''),
                    'content' => wp_kses_post($term['content'] ?? ''),
            ];
        }, $_POST['terms']);

        $terms = array_filter($terms, function($term) {
            return !empty($term['title']) || !empty($term['content']);
        });

        update_option('game_bsc_terms', array_values($terms));
    } else {
        $terms = [];
        update_option('game_bsc_terms', []);
    }

    $terms_link = sanitize_text_field($_POST['game_bsc_terms_link'] ?? '');
    update_option('game_bsc_terms_link', $terms_link);

    if ($old_terms !== $terms) {
        game_bsc_log_settings_change(
                'game_bsc_terms',
                $old_terms,
                $terms,
                'update'
        );
    }
    if ($old_terms_link !== $terms_link) {
        game_bsc_log_settings_change(
                'game_bsc_terms_link',
                $old_terms_link,
                $terms_link,
                'update'
        );
    }

    // APi Base URL
    $api_base_url = sanitize_text_field($_POST['api_base_url'] ?? '');
    update_option('game_bsc_api_base_url', $api_base_url);

    if ($old_api_base_url !== $api_base_url) {
        game_bsc_log_settings_change(
                'game_bsc_api_base_url',
                $old_api_base_url,
                $api_base_url,
                'update'
        );
    }

    // Got It settings
    $gotit_environment = sanitize_text_field($_POST['gotit_environment'] ?? 'staging');
    if (!in_array($gotit_environment, ['staging', 'production'], true)) {
        $gotit_environment = 'staging';
    }
    $gotit_api_key = sanitize_text_field($_POST['gotit_api_key'] ?? '');
    $gotit_api_key = preg_replace('/\s+/u', '', trim((string) $gotit_api_key));
    update_option('game_bsc_gotit_environment', $gotit_environment);
    update_option('game_bsc_gotit_api_key', $gotit_api_key);

    if ($old_gotit_environment !== $gotit_environment) {
        game_bsc_log_settings_change('game_bsc_gotit_environment', $old_gotit_environment, $gotit_environment, 'update');
    }
    if ($old_gotit_api_key !== $gotit_api_key) {
        game_bsc_log_settings_change('game_bsc_gotit_api_key', !empty($old_gotit_api_key) ? '[SET]' : '[EMPTY]', !empty($gotit_api_key) ? '[SET]' : '[EMPTY]', 'update');
    }
    
    $gotit_webhook_secret = sanitize_text_field($_POST['gotit_webhook_secret'] ?? '');
    update_option('game_bsc_gotit_webhook_secret', $gotit_webhook_secret);
    if ($old_gotit_webhook_secret !== $gotit_webhook_secret) {
        game_bsc_log_settings_change('game_bsc_gotit_webhook_secret', !empty($old_gotit_webhook_secret) ? '[SET]' : '[EMPTY]', !empty($gotit_webhook_secret) ? '[SET]' : '[EMPTY]', 'update');
    }



    // Trading Server URL
    $old_trading_server = get_option('game_bsc_trading_server', '');
    $trading_server = esc_url_raw($_POST['trading_server'] ?? '');
    $trading_server = rtrim($trading_server, '/'); // Remove trailing slash
    update_option('game_bsc_trading_server', $trading_server);

    if ($old_trading_server !== $trading_server) {
        game_bsc_log_settings_change(
            'game_bsc_trading_server',
            $old_trading_server,
            $trading_server,
            'update'
        );
    }

    // Default Redeemed Banner
    $old_default_redeemed_banner = get_option('game_bsc_default_redeemed_banner', '');
    $default_redeemed_banner = intval($_POST['game_bsc_default_redeemed_banner'] ?? 0);
    update_option('game_bsc_default_redeemed_banner', $default_redeemed_banner);

    if ($old_default_redeemed_banner != $default_redeemed_banner) {
        game_bsc_log_settings_change(
            'game_bsc_default_redeemed_banner',
            $old_default_redeemed_banner,
            $default_redeemed_banner,
            'update'
        );
    }

    // Default Brand Logo
    $old_default_brand_logo = get_option('game_bsc_default_brand_logo', '');
    $default_brand_logo = intval($_POST['game_bsc_default_brand_logo'] ?? 0);
    update_option('game_bsc_default_brand_logo', $default_brand_logo);

    if ($old_default_brand_logo != $default_brand_logo) {
        game_bsc_log_settings_change(
            'game_bsc_default_brand_logo',
            $old_default_brand_logo,
            $default_brand_logo,
            'update'
        );
    }

    // Banner Manager
    $old_banner_manager = get_option('game_bsc_banner_manager', '');
    $banner_manager = intval($_POST['game_bsc_banner_manager'] ?? 0);
    update_option('game_bsc_banner_manager', $banner_manager);

    if ($old_banner_manager != $banner_manager) {
        game_bsc_log_settings_change(
            'game_bsc_banner_manager',
            $old_banner_manager,
            $banner_manager,
            'update'
        );
    }

    // Banner Mobile
    $old_banner_mobile = get_option('game_bsc_banner_mobile', '');
    $banner_mobile = intval($_POST['game_bsc_banner_mobile'] ?? 0);
    update_option('game_bsc_banner_mobile', $banner_mobile);

    if ($old_banner_mobile != $banner_mobile) {
        game_bsc_log_settings_change(
            'game_bsc_banner_mobile',
            $old_banner_mobile,
            $banner_mobile,
            'update'
        );
    }

    // Banner Text
    $old_banner_text = get_option('game_bsc_banner_text', '');
    $banner_text = sanitize_text_field($_POST['game_bsc_banner_text'] ?? '');
    update_option('game_bsc_banner_text', $banner_text);

    if ($old_banner_text !== $banner_text) {
        game_bsc_log_settings_change(
            'game_bsc_banner_text',
            $old_banner_text,
            $banner_text,
            'update'
        );
    }

    // Banner Icon
    $old_banner_icon = get_option('game_bsc_banner_icon', '');
    $banner_icon = intval($_POST['game_bsc_banner_icon'] ?? 0);
    update_option('game_bsc_banner_icon', $banner_icon);

    if ($old_banner_icon != $banner_icon) {
        game_bsc_log_settings_change(
            'game_bsc_banner_icon',
            $old_banner_icon,
            $banner_icon,
            'update'
        );
    }

    // Banner Icon Mobile
    $old_banner_icon_mobile = get_option('game_bsc_banner_icon_mobile', '');
    $banner_icon_mobile = intval($_POST['game_bsc_banner_icon_mobile'] ?? 0);
    update_option('game_bsc_banner_icon_mobile', $banner_icon_mobile);

    if ($old_banner_icon_mobile != $banner_icon_mobile) {
        game_bsc_log_settings_change(
            'game_bsc_banner_icon_mobile',
            $old_banner_icon_mobile,
            $banner_icon_mobile,
            'update'
        );
    }

    // ✅ LOG THAY ĐỔI SETTINGS
    if ($old_start_date !== $start_date || $old_end_date !== $end_date) {
        game_bsc_log_settings_change(
                'game_bsc_dates',
                ['start_date' => $old_start_date, 'end_date' => $old_end_date],
                ['start_date' => $start_date, 'end_date' => $end_date],
                'update'
        );
    }

    if ($old_daily_start_time !== $daily_start_time || $old_daily_end_time !== $daily_end_time) {
        game_bsc_log_settings_change(
                'game_bsc_daily_times',
                ['daily_start_time' => $old_daily_start_time, 'daily_end_time' => $old_daily_end_time],
                ['daily_start_time' => $daily_start_time, 'daily_end_time' => $daily_end_time],
                'update'
        );
    }


    if ($old_max_wrong_answers != $max_wrong_answers) {
        game_bsc_log_settings_change(
                'game_bsc_max_wrong_answers',
                $old_max_wrong_answers,
                $max_wrong_answers,
                'update'
        );
    }

    if ($old_stages !== $new_stages) {
        game_bsc_log_settings_change(
                'game_bsc_stages',
                $old_stages,
                $new_stages,
                'update'
        );
    }

    if ($old_tasks !== $new_tasks) {
        game_bsc_log_settings_change(
                'game_bsc_tasks',
                $old_tasks,
                $new_tasks,
                'update'
        );
    }

    if ($old_rules !== $rules) {
        game_bsc_log_settings_change(
                'game_bsc_rules',
                $old_rules,
                $rules,
                'update'
        );
    }

    if ($old_rewards !== $rewards) {
        game_bsc_log_settings_change(
                'game_bsc_rewards_descriptions',
                $old_rewards,
                $rewards,
                'update'
        );
    }

    if ($old_price_drop_rate != $piece_drop_rate) {
        game_bsc_log_settings_change(
                'game_bsc_piece_drop_rate',
                $old_price_drop_rate,
                $piece_drop_rate,
                'update'
        );
    }

    if ($old_day_allowed_to_play_game_after_period_ends != $day_allowed_to_play_game_after_period_ends) {
        game_bsc_log_settings_change(
                'game_bsc_day_allowed_to_play_game_after_period_ends',
                $old_day_allowed_to_play_game_after_period_ends,
                $day_allowed_to_play_game_after_period_ends,
                'update'
        );
    }

    return game_bsc_redirect_result(__('Lưu cài đặt thành công.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
}