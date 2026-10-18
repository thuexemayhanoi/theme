<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class AutopilotTest extends TestCase {
    public function test_verified_prices_and_followup(): void {
        $answer = nt_assistant_answer('Giá Honda Vision theo tháng?');
        $this->assertSame('prices', $answer['intent']);
        $this->assertStringContainsString('1.800.000–2.000.000đ', $answer['answer']);
        $this->assertStringContainsString('1.000.000đ', nt_assistant_answer('Còn tuần?', $answer['context'])['answer']);
        $this->assertStringNotContainsString('Honda Wave', $answer['answer']);
    }
    public function test_commercial_intents_and_no_fabricated_booking(): void {
        foreach (array('Xe ga 50cc'=>'vehicles','Giá xe máy điện'=>'prices','Tiền đặt cọc'=>'deposit','Giao xe Phố Cổ'=>'location','Mở cửa lúc mấy giờ'=>'hours','Tôi muốn đặt xe ngày mai'=>'booking','Xe bị thủng lốp'=>'support') as $q=>$intent) {
            $r = nt_assistant_answer($q);
            $this->assertSame($intent,$r['intent'],$q);
            $this->assertSame('local-rules-and-search',$r['mode']);
        }
        $this->assertStringContainsString('cannot confirm', nt_assistant_answer('I want to book a scooter')['answer']);
        $this->assertStringContainsString('Liên hệ', nt_assistant_answer('Giá xe điện')['answer']);
    }
    public function test_index_excludes_drafts_passwords_and_unpublished_results(): void {
        $id = wp_insert_post(array('post_title'=>'NT knowledge unique fixture','post_content'=>'Published knowledge fixture.','post_status'=>'publish'));
        try {
            $this->assertIsArray(get_post_meta($id,'_nt_knowledge',true));
            wp_update_post(array('ID'=>$id,'post_status'=>'draft'));
            $this->assertSame('',get_post_meta($id,'_nt_knowledge',true));
            $this->assertSame(array(),nt_assistant_sources('NT knowledge unique fixture'));
            wp_update_post(array('ID'=>$id,'post_status'=>'publish','post_password'=>'secret'));
            $this->assertSame('',get_post_meta($id,'_nt_knowledge',true));
        } finally { wp_delete_post($id,true); }
    }
    public function test_routes_validate_input_and_limit_spam(): void {
        if (!did_action('rest_api_init')) do_action('rest_api_init');
        add_filter('nguyentu_chat_widget_active','__return_false');
        $request = new WP_REST_Request('POST','/nguyentu/v1/assistant');
        $request->set_param('question',str_repeat('x',301));
        $response = rest_get_server()->dispatch($request);
        $this->assertSame(400,$response->get_status());
        $request->set_param('question','Giá Vision theo tháng?');
        $ip = '127.0.0.1';
        $_SERVER['REMOTE_ADDR']=$ip;
        $key='nt_rate_'.hash_hmac('sha256',$ip,wp_salt('nonce'));
        delete_transient($key);
        try {
            $this->assertSame(200,rest_get_server()->dispatch($request)->get_status());
            set_transient($key,20,60);
            $this->assertSame(429,rest_get_server()->dispatch($request)->get_status());
        } finally { delete_transient($key); remove_filter('nguyentu_chat_widget_active','__return_false'); }
    }
    public function test_hours_response_is_not_cacheable(): void {
        if (!did_action('rest_api_init')) do_action('rest_api_init');
        $r=rest_get_server()->dispatch(new WP_REST_Request('GET','/nguyentu/v1/hours'));
        $this->assertSame(200,$r->get_status());
        $this->assertStringContainsString('no-store',$r->get_headers()['Cache-Control']);
    }
}
