<?php
/**
 * 专家审稿控制器
 * @authors 107Lab-小志 (764000395@qq.com)
 * @date    2018-04-09 15:41:58
 * @version $Id$
 */
defined('BASEPATH') OR exit('No direct script access allowed');
class Specialist extends MY_Controller {

	function __construct() {
		parent::__construct();
		if ($this->session->userdata('identity') != 'specialist') {
			alert_msg('请以专家身份登录系统', 'go', site_url('home/login'));
		}
		$this->load->model('index_model');
	}

	/*
		稿件列表
	 */
	public function list_article($action) {
		$where_arr['suggest.user_id'] = $this->session->userdata('user_id');
		if ($action == 'check') {
			//需要审核的文章
			$where_arr['status is null'] = null;
			$view_html = 'specialist/list_article_check.html';
		} else {
			//已经审核过的文章
			$view_html = 'specialist/list_article_checked.html';
		}
		$data['article'] = $this->index_model->get_check_article($where_arr);
		$this->load->view($view_html, $data);
	}

	/*
		查看文章
	 */
	public function article($action, $article_id) {
		switch ($action) {
		case 'check': //要审核的文章
			$view_html = 'specialist/check_article.html';
			break;
		default: //审核过的文章
			$view_html = 'specialist/article_checked.html';
			$data['suggest'] = $this->index_model->get_name_suggest(array('article_id' => $article_id, 'suggest.user_id' => $this->session->userdata('user_id')));
			break;
		}
		if (!is_numeric($article_id)) {
			alert_msg('该稿件不存在！');
		}
		$article = $this->index_model->get_info_article(array('article_id' => $article_id));
		if (empty($article)) {
			alert_msg('该稿件不存在');
		}
		$data['article'] = $article[0];
		$this->load->view($view_html, $data);
	}

	/*
		在线审稿 登录后审稿
	 */
	public function check($article_id, $type) {
		if (!is_numeric($article_id)) {
			alert_msg('该稿件不存在！');
		}

		//获取稿件的审核状态 并判断该稿件是否存在 方便下通过article_id、user_id、rank查找唯一稿件
		$article = $this->index_model->get_info_article(array('article_id' => $article_id));
		if (empty($article)) {
			alert_msg('该稿件不存在！');
		}
		$user_id = $this->session->userdata('user_id');
		$where_arr = array('article_id' => $article_id, 'user_id' => $user_id, 'rank' => $article[0]['check_status']);

		//判断是否是被指定的该稿件的审稿专家
		$suggest = $this->index_model->get_suggest_info($where_arr);
		if (empty($suggest)) {
			alert_msg('权限不足，不能审核该稿件');
		}

		//执行审稿操作
		$data = array(
			'content' => $this->input->post('content'),
			'time' => time(),
		);
		$data['status'] = $type == 'pass' ? 1 : 0;
		$status = $this->db->update('suggest', $data, array('sug_id' => $suggest[0]['sug_id']));
		if ($status) {
			//判断两个专家的审核意见，如果都通过审核，则稿件进入下一轮审核。如果都不通过审核，直接拒稿
			//若两专家审核意见不一致，交由编委会决定是拒稿，还是反修。
			$other_suggest = $this->index_model->get_suggest_info(array('article_id' => $article_id, 'rank' => $article[0]['check_status'], 'user_id !=' => $user_id));
			if (is_numeric($other_suggest[0]['status'])) {
				if ($other_suggest[0]['status'] == $check_code) {
					if ($check_code == 1) {
						//通过审核，通过修改审进度状态码，使该稿件进入下一轮审核
						$article_data['check_status'] = $article[0]['check_status'] + 1;
					} else {
						//两个专家都不通过，直接拒稿
						$article_data['check_status'] = -1;
					}
				} else {
					//-10=》初审疑问稿件， -11=》复审疑问稿件
					$article_data['check_status'] = -$article[0]['check_status'] - 10;
				}

				//两个专家都审核完后，将稿件设置成未指定状态，并将审核token设为空
				$article_data['allot_status'] = 0;
				$article_data['check_token'] = '';

				$this->db->update('article', $article_data, array('article_id' => $article_id));
			}
			alert_msg('您的审核意见已提交，感谢您参与审核！');
		} else {
			alert_msg('审核失败，请检查您的网络！');
		}
	}

	/*
		下载稿件
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

	/*
		专家以作者身份登录
	 */
	public function login_by_author() {
		$this->session->set_userdata('identity', 'author');
		header('location:' . site_url('index/myhome'));
	}
}