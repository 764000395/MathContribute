<?php
/**
 * 编委定稿控制器
 * @authors 杨志颖 (764000395@qq.com)
 * @date    2018-04-09 18:58:43
 * @version $1$
 */

class Editorial extends MY_Controller {

	function __construct() {
		parent::__construct();
		if ($this->session->userdata('identity') != 'editorial') {
			alert_msg('请以编委身份登录系统', 'go', site_url('home/login'));
		}
		$this->load->model('index_model');
	}

	/*
		稿件列表  类似Author.php的list_article()方法
	 */
	public function list_article($type, $offset = 0) {
		$where_arr = array();
		$view_html = 'list_article.html';
		switch ($type) {
		case 'all': //全部稿件
			$where_arr = array();
			break;
		case 'wait_check': //未完成审核 != -1 && <3
			$where_arr = array('check_status !=' => '-1', 'check_status <' => 3);
			break;
		case 'use': //录用稿件 =3
			$where_arr = array('check_status' => '3');
			break;
		case 'refuses': //被拒稿件 =-1
			$where_arr = array('check_status' => '-1');
			break;
		case 'edit': //返修稿件
			$where_arr = array('check_status <' => '-1');
			break;
		case 'assign_first': //指定初审
			$where_arr = array('check_status' => 0, 'allot_status' => 0);
			$view_html = 'list_article_check.html';
			$data['assign_rank'] = 'assign_first';
			break;
		case 'assign_second': //指定复审
			$where_arr = array('check_status' => 1, 'allot_status' => 0);
			$view_html = 'list_article_check.html';
			$data['assign_rank'] = 'assign_second';
			break;
		case 'finalize': //编委会定稿
			$where_arr = array('check_status' => 2);
			$view_html = 'list_article_check.html';
			$view_html = 'list_article_finalize.html';
			break;
		case 'doubt': //疑问稿件 -10=>初审疑问稿件 -11=>复审疑问稿件
			$where_arr = array('check_status <' => '-9');
			$view_html = 'list_article_doubt.html';
			$data['assign_rank'] = 'doubt';
			break;
		}
		$per_page = 10; //每页显示10条
		$page_url = site_url('index/editorial/list_article/' . $type); //分页地址url
		$total_rows = $this->db->where($where_arr)->count_all_results('article'); //共多少条数据
		$offset_uri_segment = 5;

		//获取分页
		$this->load->library('myclass');
		$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);
		$data['total_rows'] = $total_rows;

		//获取文章列表信息
		$other_info = ', check_deadline'; //其他要获取的字段 以 "," 开头
		$data['article'] = $this->index_model->get_list_article($where_arr, $offset, $per_page, $other_info);
		$this->load->view('editorial/' . $view_html, $data);
	}

	/*
		查看文章
	 */
	public function article($action, $article_id) {
		if (!is_numeric($article_id)) {
			alert_msg('无权访问');
		}
		$where_arr = array('article_id' => $article_id);
		$status = $this->index_model->get_info_article($where_arr, '');
		empty($status) ? alert_msg('该稿件已被删除') : $data['article'] = $status[0];
		switch ($action) {
		case 'assign_first': //指定初审
			$view_html = 'editorial/assign_article.html';
			$where_arr = array('identity' => 'specialist', 'status' => 1);
			$data['specialist'] = $this->index_model->get_specialist_info($where_arr);
			$data['suggest'] = array();
			break;
		case 'assign_second': //指定复审
			$view_html = 'editorial/assign_article.html';

			//获取专家名字
			$where_arr = array('identity' => 'specialist', 'status' => 1);
			$data['specialist'] = $this->index_model->get_specialist_info($where_arr);

			//获取一审专家名字和意见 内连接 INNER JOIN
			$data['suggest'] = $this->index_model->get_name_suggest(array('article_id' => $article_id));
			break;
		case 'finalize':
			$view_html = 'editorial/finalize_article.html';
			//获取一审专家名字和意见 内连接 INNER JOIN
			$data['suggest'] = $this->index_model->get_name_suggest(array('article_id' => $article_id));
			break;
		case 'doubt':
			$view_html = 'editorial/doubt_article.html';
			//获取一审专家名字和意见 内连接 INNER JOIN
			$data['suggest'] = $this->index_model->get_name_suggest(array('article_id' => $article_id));
			break;
		default:
			$view_html = 'editorial/info_article.html';
			//获取一审专家名字和意见 内连接 INNER JOIN
			$data['suggest'] = $this->index_model->get_name_suggest(array('article_id' => $article_id));
			break;
		}

		$this->load->view($view_html, $data);
	}

	/*
		指定专家审稿
	 */
	public function set_check() {
		$set_arr = $this->input->post('set_specialist');
		$article_id = $this->input->post('article_id');

		//判断传过来的参数是否合法 set_arr数组里存的是两个专家的id 必须为数字
		if (count($set_arr) == 2) {
			!is_numeric($article_id) || !is_numeric($set_arr[0]) || !is_numeric($set_arr[1]) ? alert_msg('权限不足') : '';
		} else {
			alert_msg('必须指定两个专家审核');
		}
		//判断专家是否存在
		$specialist1 = $this->db->select('user_id, realname, email')->get_where('user', array('user_id' => $set_arr[0]))->result_array();
		if (empty($specialist1)) {
			alert_msg('您指定的专家不存在');
		}
		$specialist2 = $this->db->select('user_id, realname, email')->get_where('user', array('user_id' => $set_arr[1]))->result_array();
		if (empty($specialist1)) {
			alert_msg('您指定的专家不存在');
		}
		//查看要指定审核的稿件是否已经存在，或者已经指定过转件审核
		$article = $this->index_model->get_info_article(array('article_id' => $article_id, 'allot_status' => 0));
		empty($article) ? alert_msg('该稿件已经指定专家审核，请勿重复指定！') : $article = $article[0];

		//判断稿件是否处于正在审核状态
		$article['check_status'] >= 0 && $article['check_status'] <= 2 ? '' : alert_msg('该稿件已经通过专家审核');

		//执行指定专家审核
		$status1 = $this->db->insert('suggest', array('article_id' => $article_id, 'user_id' => $set_arr[0], 'rank' => $article['check_status']));
		$status2 = $this->db->insert('suggest', array('article_id' => $article_id, 'user_id' => $set_arr[1], 'rank' => $article['check_status']));
		if ($status1 && $status2) {
			$check_token = md5(time() . mt_rand(1000, 9999)); //专家不登录检查稿件凭据
			$this->db->update('article', array('allot_status' => 1, 'check_token' => $check_token), array('article_id' => $article_id));

			//给指定审稿的两个专家发邮件提醒专家审核
			$check_url1 = site_url('home/check/see/' . $article_id . '/' . $specialist1[0]['user_id'] . '/' . $check_token);
			$check_url2 = site_url('home/check/see/' . $article_id . '/' . $specialist2[0]['user_id'] . '/' . $check_token);
			$subject = '数学季刊投稿系统提醒您审核稿件';
			$to1 = $specialist1[0]['email']; //专家1邮箱
			$to2 = $specialist2[0]['email']; //专家2邮箱
			echo $to1 . '<br>' . $to2;
			$begin = '尊敬的';
			$end_b = '专家您好，请您访问 ';
			$end_e = ' 或登录系统来审核稿件!';
			$message1 = $begin . $specialist1[0]['realname'] . $end_b . $check_url1 . $end_e;
			$message2 = $begin . $specialist2[0]['realname'] . $end_b . $check_url2 . $end_e;
			$this->load->library('myclass');
			$this->myclass->send_email($to1, $subject, $message1);
			$this->myclass->send_email($to2, $subject, $message2);

			alert_msg('指定成功');
		} else {
			alert_msg('指定失败，该稿件可能已经被指定');
		}
	}

	/*
		编委在线定稿
		合格——录用=》use， 不合格——返修=》edit， 不合格——拒稿=》refuses
	 */
	public function finalize($article_id, $type) {
		if (!is_numeric($article_id)) {
			$array = array(
				'code' => 400,
				'message' => '权限不足',
			);
			echo json_encode($array, JSON_UNESCAPED_UNICODE);exit;
		}
		$suggest = $this->input->post('suggest');
		$isset_finalize = $this->db->select('sug_id')->get_where('suggest', array('article_id' => $article_id, 'user_id' => $this->session->userdata('user_id')))->result_array();
		if ($type == 'use') {
			//合格——录用

			//判断返修前后是否为同一个编委会成员提交的审核，若是=》修改表。否则插入审核信息
			if (empty($isset_finalize)) {
				$this->db->insert('suggest', array('article_id' => $article_id, 'user_id' => $this->session->userdata('user_id'), 'content' => $suggest, 'rank' => 3, 'status' => 1, 'time' => time()));
			} else {
				$this->db->update('suggest', array('content' => $suggest, 'rank' => 3, 'status' => 1, 'time' => time()), array('sug_id' => $isset_finalize[0]['sug_id']));
			}

			//将稿件的审核进度设置为编委会审核完成，即该篇稿件已被录用
			$status = $this->db->update('article', array('check_status' => 3, 'use_time' => time()), array('article_id' => $article_id));
		} else if ($type == 'edit') {
			//不合格——返修
			if (empty($isset_finalize)) {
				$this->db->insert('suggest', array('article_id' => $article_id, 'user_id' => $this->session->userdata('user_id'), 'content' => $suggest, 'rank' => -4, 'status' => 0, 'time' => time()));
			} else {
				$this->db->update('suggest', array('content' => $suggest, 'rank' => -4, 'status' => 0, 'time' => time()), array('sug_id' => $isset_finalize[0]['sug_id']));
			}

			//将稿件的审核进度设置为编委会要求返修，进度状态码为-4
			$status = $this->db->update('article', array('check_status' => -4), array('article_id' => $article_id));
		} else {
			//不合格——拒稿
			if (empty($isset_finalize)) {
				$this->db->insert('suggest', array('article_id' => $article_id, 'user_id' => $this->session->userdata('user_id'), 'content' => $suggest, 'rank' => -1, 'status' => 0, 'time' => time()));
			} else {
				$this->db->update('suggest', array('content' => $suggest, 'rank' => -1, 'status' => 0, 'time' => time()), array('sug_id' => $isset_finalize[0]['sug_id']));
			}

			//将稿件的审核进度设置为编委拒稿，进度状态码为-1
			$status = $this->db->update('article', array('check_status' => -1), array('article_id' => $article_id));
		}
		if ($status) {
			$array = array(
				'code' => 200,
				'message' => '感谢您参与审核该稿件！',
			);

			//待定功能，在线定稿成功后要不要发邮件通知作者？

		} else {
			$array = array(
				'code' => 400,
				'message' => '审核失败，请稍后重试！',
			);
		}
		echo json_encode($array, JSON_UNESCAPED_UNICODE);exit;
	}

	/*
		疑问稿件处理
		通过本次审核、返修、拒稿
	 */
	public function doubt_article($article_id, $type) {
		if (!is_numeric($article_id)) {
			alert_msg('该稿件不存在');
		}
		$article_status = $this->index_model->get_info_article(array('article_id' => $article_id));
		if (empty($article_status)) {
			alert('该稿件不存在');
		}
		//-10-$article_status[0]['check_status'] => 稿件当前状态
		if ($type == 'pass') {
			//-10-$article_status[0]['check_status']+1 => 进入下一个状态
			$data = array('check_status' => (-9 - $article_status[0]['check_status']));
		} else if ($type == 'edit') {
			//-(-10-$article_status[0]['check_status'])-2 => 进入返修状态
			$data = array('check_status' => (8 + $article_status[0]['check_status']));
		} else {
			//check_status => -1 拒稿状态
			$data = array('check_status' => -1);
		}

		//对article表执行修改操作
		$status = $this->db->update('article', $data, array('article_id' => $article_id));
		if ($status) {
			alert_msg('操作成功！');
		} else {
			alert_msg('操作失败，请重试！');
		}
	}

	/*
		下载稿件，没有权限限制,任何一个都可以下载
	 */
	public function download($article_id) {
		//article_id必须为数字，防止sql注入
		if (!is_numeric($article_id)) {
			alert_msg('无法进行下载！', 'close');
		}

		$where_arr = array('article_id' => $article_id);
		$article = $this->index_model->get_info_article($where_arr);
		if (empty($article)) {
			alert_msg('该稿件不存在，无法完下载！');
		}

		$this->load->helper('download');
		$data = file_get_contents($this->config->item('MYPATH') . $article[0]['attachment_url']);

		//匹配文件后缀名，重命名下载
		preg_match('/.\w+$/', $article[0]['attachment_url'], $matches);
		force_download($article[0]['title'] . $matches[0], $data);
	}
	private function _get_where_arr_by_type($type) {
		$where_arr = '';

		return $where_arr;
	}
}