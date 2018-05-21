<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller {
	public function __construct() {
		parent::__construct();
		$this->load->model('index_model');
	}

	/************************ 前端内容 Begin ************************/
	//首页
	public function index() {
		//友情链接
		$data['link'] = $this->index_model->get_content_list(array('col_id >=' => 21, 'col_id <=' => 24));

		//最新录用
		$data['latest_article'] = $this->index_model->get_list_article_season(array('check_status' => 3), 0, 6);

		//当期目录
		$data['now_article'] = $this->index_model->get_list_article_season(array('use_time >=' => get_season_time(time(), 'start'), 'use_time <=' => get_season_time(time(), 'end'), 'check_status' => 3), 0, 6);

		//下期目录
		$data['next_article'] = $this->index_model->get_list_article_season(array('season' => 'next'), 0, 6);

		//过刊浏览 按照日期升序排列
		$data['overdue_article'] = $this->db->order_by('use_time ASC')->limit(6, 0)->get_where('article', array('check_status' => 3))->result_array();
		$this->load->view('index/index.html', $data);
	}

	//查看稿件 或 列表稿件 在线期刊
	public function article($action, $id = 0) {
		if (!is_numeric($id)) {
			alert_msg('您访问的内容不存在！', 'go', base_url());
		}
		//查看稿件
		if ($action == 'see') {
			//获取稿件信息
			$article = $this->index_model->get_article_info(array('article_id' => $id));
			//判断稿件是否存在
			if (empty($article)) {
				alert_msg('您访问的内容不存在！', 'go', base_url());
			}

			//阅读量+1
			$this->db->update('article', array('read_total' => $article[0]['read_total'] + 1), array('article_id' => $id));

			$data['article'] = $article[0];
			$this->load->view('index/article.html', $data);
		} else {
			switch ($action) {
			case 'now_list':
				$where_arr = array('use_time >=' => get_season_time(time(), 'start'), 'use_time <=' => get_season_time(time(), 'end'), 'check_status' => 3);
				$col_name = '当期目录';
				break;
			case 'next_list':
				$where_arr = array('season' => 'next');
				$col_name = '下期目录';
				break;
			case 'overdue_list':
				$where_arr = array('check_status' => 3);
				$data['article'] = $this->db->order_by('use_time ASC')->limit(6, 0)->get_where('article', array('check_status' => 3))->result_array();
				$col_name = '过刊浏览';
				break;
			default:
				$where_arr = array('check_status' => 3);
				$col_name = '最新录用';
				break;
			}
			$offset = $id;
			//获取分页链接
			$page_url = site_url('home/article/' . $action);
			$total_rows = $this->db->where($where_arr)->count_all_results('article');
			$offset_uri_segment = 3;
			$per_page = 10;
			$this->load->library('myclass');
			$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);

			//获取列表稿件信息
			if ($action != 'overdue_list') {
				$data['article'] = $this->index_model->get_list_article_season($where_arr, $offset, $per_page);
			}

			$data['col_name'] = $col_name;
			$this->load->view('index/article_list.html', $data);
		}
	}

	//在线期刊 各年目次文章列表
	public function year($year, $season, $offset = 0) {
		if (!is_numeric($year) || !is_numeric($season) || $season < 1 || $season > 4 || $year < 2018) {
			alert_msg('您访问的内容不存在！', 'close');
		}
		$time = mktime(0, 0, 0, $season * 3, 1, $year);
		//print_r($season);die;
		$where_arr = array('use_time >=' => get_season_time($time, 'start'), 'use_time <=' => get_season_time($time, 'end'), 'check_status' => 3);

		$data['col_name'] = $year . '年 第 ' . $season . ' 期';

		$page_url = site_url("home/year/$year/$season");
		$total_rows = $this->db->where($where_arr)->count_all_results('article');
		$offset_uri_segment = 5;
		$per_page = 10;
		$this->load->library('myclass');
		$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);

		$data['article'] = $this->index_model->get_list_article_season($where_arr, $offset, $per_page);

		$this->load->view('index/article_list.html', $data);
	}

	public function year_list($offset = 0) {
		$page_url = site_url('home/year_list');
		$total_rows = date('Y') - 2017;
		$offset_uri_segment = 3;
		$per_page = 10;
		$this->load->library('myclass');
		$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);
		$data['season'] = array(1, 2, 3, 4);
		$this_year = date('Y');
		for ($i = $this_year; $i >= 2018; $i--) {
			$data['year'][] = $i;
		}
		$data['col_name'] = '各年目次';
		$this->load->view('index/year_list.html', $data);
	}

	//快速检索
	public function search() {
		$search = $this->input->post('search');
		$data['article'] = $this->index_model->get_search_list($search);
		$data['link'] = '';
		$data['col_name'] = '搜索结果';
		$this->load->view('index/article_list.html', $data);
	}

	//在线留言
	public function comment($action = 'see') {
		if ($action == 'do') {
			//判断留言是否过于频发
			if (!empty($this->session->tempdata('done'))) {
				$array = array(
					'code' => 400,
					'message' => '对不起，您的留言过于频繁，请5分钟后再试！',
				);
				$this->_get_type($array);
			}

			//留言内容不能超过256个字
			$content = $this->input->post('content');
			if (mb_strlen($content) > 256) {
				$array = array(
					'code' => 400,
					'message' => '对不起，留言内容请不要超过256个字！',
				);
				$this->_get_type($array);
			}

			//执行留言操作
			$data = array(
				'realname' => $this->input->post('realname'),
				'email' => $this->input->post('email'),
				'content' => $content,
				'time' => time(),
			);
			if ($this->db->insert('comment', $data)) {
				$array = array(
					'code' => 200,
					'message' => '恭喜您留言成功，我们会尽快给您回复！',
				);
				//设置5分钟的session 防止留言过于频繁
				$this->session->set_tempdata('done', 'have_done', 300);
			} else {
				$array = array(
					'code' => 400,
					'message' => '留言失败，请检查您的网络！',
				);
			}
			$this->_get_type($array);

		} else {
			$this->load->view('index/comment.html');
		}
	}

	//栏目直接对应内容页
	public function content($col_id) {
		if (!is_numeric($col_id)) {
			alert_msg('您访问的内容不存在！', 'go', base_url());
		}

		//获取栏目信息
		$pid = $this->index_model->get_col_info(array('col_id' => $col_id));
		if (empty($pid)) {
			alert_msg('您访问的内容不存在！', 'go', base_url());
		}
		$data['this_col_name'] = $pid[0]['col_name'];
		//判断是否为1级栏目
		if ($pid[0]['pid'] != 0) {
			$data['col'] = $this->index_model->get_col_info(array('pid' => $pid[0]['pid']));
		} else {
			$data['col'] = $pid;
		}

		//该栏目内容
		$content = $this->index_model->get_content_info(array('col_id' => $col_id));
		if (empty($content)) {
			alert_msg('您访问的信息不存在');
		}
		$data['content'] = $content[0];
		$this->load->view('index/content.html', $data);
	}

	//作者指南 审者指南  下载中心
	public function list($col_id, $offset = 0) {
		if (!is_numeric($col_id)) {
			alert_msg('您访问的内容不存在！', 'go', base_url());
		}
		$col = $this->index_model->get_col_info(array('col_id' => $col_id));
		if (empty($col)) {
			alert_msg('您访问的内容不存在！', 'go', base_url());
		}
		$data['col'] = $col[0];

		$where_arr = array('col_id' => $col_id);
		//获取分页link
		$page_url = site_url('home/list/' . $col_id);
		$total_rows = $this->db->where($where_arr)->count_all_results('content');
		$offset_uri_segment = 4;
		$per_page = 10;
		$this->load->library('myclass');
		$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);

		//获取内容
		$data['content'] = $this->index_model->get_content_list($where_arr, $offset, $per_page, 'fenye');
		$this->load->view('index/list.html', $data);
	}

	//列表内容
	public function info($id) {
		if (!is_numeric($id)) {
			alert_msg('您访问的信息不存在！');
		}

		$content = $this->index_model->get_content_info(array('id' => $id));
		if (empty($content)) {
			alert_msg('您访问的信息不存在！');
		}

		$data['content'] = $content[0];
		$data['col'] = $this->index_model->get_col_info(array('col_id' => $content[0]['col_id']))[0];
		$this->load->view('index/info.html', $data);
	}

	//链接列表
	public function link($col_id, $offset = 0) {
		if (!is_numeric($col_id)) {
			alert_msg('您访问的内容不存在！', 'go', base_url());
		}

		//获取友情链接下所有栏目信息
		$data['col'] = $this->index_model->get_col_info(array('pid' => 8));

		//匹配当前栏目获取栏目名称
		foreach ($data['col'] as $c) {
			if ($c['col_id'] == $col_id) {
				$data['this_col_name'] = $c['col_name'];
				break;
			}
		}

		$where_arr = array('col_id' => $col_id);
		//获取分页link
		$page_url = site_url('home/link/' . $col_id);
		$total_rows = $this->db->where($where_arr)->count_all_results('content');
		$offset_uri_segment = 4;
		$per_page = 10;
		$this->load->library('myclass');
		$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);

		//获取内容
		$data['content'] = $this->index_model->get_content_list($where_arr, $offset, $per_page, 'fenye');
		$this->load->view('index/link.html', $data);
	}
	/************************ 前端内容 End ************************/

	/************************ 前台业务逻辑 Begin ************************/
	/*
		注册 register
	 */
	public function register($action = '', $identity = 'author') {
		if ($action == 'do') {
			$password = $this->input->post('pwd');
			$re_password = $this->input->post('re_pwd');
			//判断两次密码输入是否一致
			if ($password != $re_password) {
				$array = array(
					'code' => 400,
					'message' => '两次输入密码不一致',
				);
				$this->_get_type($array);
			}

			//检查邮箱是否已经被注册
			$email = $this->input->post('email');
			$isset_email = $this->db->select('user_id')->get_where('user', array('email' => $email))->result_array();
			if (!empty($isset_email)) {
				$array = array(
					'code' => 400,
					'message' => '该邮箱已经注册，请直接登录',
				);
				$this->_get_type($array);
			}
			$register_info = array(
				'email' => $email,
				'password' => md5($password),
				'realname' => $this->input->post('realname'),
				'sex' => $this->input->post('sex'),
				'major' => $this->input->post('major'),
				'research_direction' => $this->input->post('research_direction'),
				'address' => $this->input->post('address'),
				'phone' => $this->input->post('phone'),
				'postcode' => $this->input->post('postcode'),
				'organization' => $this->input->post('organization'),
				'qq' => $this->input->post('qq'),
				'edu_background' => $this->input->post('edu_background'),
				'identity' => $this->input->post('identity'),
				'register_time' => time(),
			);

			//如果注册的用户不是作者，将用户状态设置为0，需要通过后台管理员审核后才可使用
			$register_info['status'] = $register_info['identity'] == 'author' ? 1 : 0;
			$status = $this->db->insert('user', $register_info);
			if ($status) {
				if ($register_info['status'] == 0) {
					$array = array(
						'code' => 400,
						'message' => '注册成功，我们会尽快为您审核，请耐心等待！',
					);
				} else {
					$this->session->set_userdata(array('user_id' => $status, 'email' => $email, 'identity' => $register_info['identity']));
					$array = array(
						'code' => 200,
						'message' => '注册成功',
					);
				}
			} else {
				$array = array(
					'code' => 400,
					'message' => '注册失败，请检查信息是否输入正确',
				);
			}
			$this->_get_type($array);
		} else {
			$data['identity'] = $identity;
			$this->load->view('index/register.html', $data);
		}

	}

	/*
		登录接口
	 */
	public function login_api() {
		$authcode = $this->session->userdata('user_authcode');
		$post_authcode = $this->input->post('authcode');
		if (empty($post_authcode) || $authcode != $post_authcode) {
			$array = array(
				'code' => 400,
				'message' => '验证码错误',
			);
			$this->_get_type($array);
		}
		$email = $this->input->post('email');
		$password = $this->input->post('password');
		$status = $this->index_model->get_user_info($email, $password);
		if (empty($status)) {
			$array = array(
				'code' => 400,
				'message' => '用户名或密码不正确',
			);
			$this->_get_type($array);
		} elseif ($status[0]['status'] != 1) {
			$array = array(
				'code' => 400,
				'message' => '管理员正在审核中！审核完成后我们发邮件通知您，请耐心等待！',
			);
			$this->_get_type($array);
		} else {
			$user_session = array(
				'user_id' => $status[0]['user_id'],
				'realname' => $status[0]['realname'],
				'identity' => $status[0]['identity'],
			);
			$this->session->set_userdata($user_session);
			$array = array(
				'code' => 200,
				'message' => '登录成功',
			);
			$this->_get_type($array);
		}
	}

	/*
		登陆界面
	 */
	public function login() {
		$this->load->view('index/login.html');
	}

	/*
		检查登录状态
	 */
	public function login_test($identity, $type = '') {
		if ($identity == $this->session->userdata('identity')) {
			if ($type == 'ajax') {
				$data = array(
					'code' => 200,
					'message' => '请先登录！',
				);
				$this->_get_type($data);
			} else {
				header('location:' . site_url('index/myhome'));
			}
		} else {
			if ($type == 'ajax') {
				$data = array(
					'code' => 400,
					'message' => '请先登录！',
				);
				$this->_get_type($data);
			} else {
				header('location:' . site_url('home/login'));
			}
		}
	}
	/*
		忘记密码
	 */
	public function forget_password($action = 'see') {
		if ($action == 'do') {
			$post_email_authcode = $this->input->post('email_authcode');

			//判断邮箱验证码是否输入正确
			if ($post_email_authcode != $this->session->userdata('email_authcode')) {
				$array = array(
					'code' => 400,
					'message' => '邮箱验证码输入错误，请仔细查看！',
				);
				$this->_get_type($array);
			}

			$new_password = $this->input->post('password');
			$re_password = $this->input->post('re_password');

			//判断两次密码是否输入一致
			if ($new_password != $re_password) {
				$array = array(
					'code' => 400,
					'message' => '两次输入密码不一致',
				);
				$this->_get_type($array);
			}

			//执行修改密码操作
			$status = $this->db->update('user', array('password' => md5($new_password)), array('user_id' => $this->session->userdata('user_id')));
			if ($status) {
				$array = array(
					'code' => 200,
					'message' => '密码修改成功，请登录！',
				);
				$this->session->sess_destory();
			} else {
				$array = array(
					'code' => 400,
					'message' => '密码修改失败，请稍后重试！',
				);
			}
			$this->_get_type($array);
		} else {
			$this->load->view('index/forget_password.html');
		}
	}

	/*
		获取邮箱验证码
	 */
	public function get_email_authcode() {
		$post_authcode = $this->input->post('authcode');
		$authcode = $this->session->userdata('user_authcode');
		//验证码是否输入正确
		if ($post_authcode != $authcode) {
			$array = array(
				'code' => 400,
				'message' => '您输入的验证码不正确',
			);
			$this->_get_type($array);
		}

		//限制邮箱验证码3分钟发一次
		if (!empty($this->session->tempdata('sended_email'))) {
			$array = array(
				'code' => 400,
				'message' => '邮箱验证码已发送，请注意查收。',
			);
			$this->_get_type($array);
		}
		//邮箱是否存在
		$email = $this->input->post('email');
		$status = $this->db->select('user_id, realname')->get_where('user', array('email' => $email))->result_array();
		if (empty($status)) {
			$array = array(
				'code' => 400,
				'message' => '您输入的邮箱不正确！',
			);
			$this->_get_type($array);
		}

		//发送邮箱验证码
		$email_authcode = mt_rand(100000, 999999);

		$subject = '邮箱验证码——数学季刊投稿系统';
		$message = '尊敬的' . $status[0]['realname'] . '您好，你正在进行修改登录密码操作，您的邮箱验证码为：' . $email_authcode . ' 请勿将此验证码发送给任何人。';
		$this->load->library('myclass');
		if ($this->myclass->send_email($email, $subject, $message)) {
			$this->session->set_userdata(array('user_id' => $status[0]['user_id'], 'email_authcode' => $email_authcode));
			$this->session->set_tempdata('sended_email', '1', 180);
			$array = array(
				'code' => 200,
				'message' => '邮箱验证码已发送！',
			);
		} else {
			$array = array(
				'code' => 400,
				'message' => '邮箱验证码发送失败，请稍后重试！',
			);
		}
		$this->_get_type($array);
	}

	//专家不登录的状态下审核
	public function check($action, $article_id, $user_id, $token) {
		if (!is_numeric($article_id) || !is_numeric($user_id)) {
			alert_msg('该稿件不存在！', 'close');
		}

		//判断文章是否存在
		$article = $this->index_model->get_article_info(array('article_id' => $article_id, 'check_token' => $token));
		if (empty($article)) {
			alert_msg('你的审核意见已经提交，请勿重复审核！');
		}

		//查看 OR 执行审核操作
		if ($action == 'see') {
			//查看操作
			$data = array(
				'article' => $article[0],
				'specialist' => $user_id,
				'token' => $token,
			);
			$this->load->view('index/check_article.html', $data);
		} else if ($action == 'check') {
			//判断审核信息是否正确 防止越权
			$suggest = $this->index_model->get_suggest_info(array('user_id' => $user_id, 'article_id' => $article_id, 'token' => $token));
			if (empty($suggest)) {
				$array = array(
					'code' => 400,
					'message' => '审核信息错误，请核对链接是否正确！',
				);
				$this->_get_type($array);
			}

			if (!empty($suggest[0]['status'])) {
				$array = array(
					'code' => 400,
					'message' => '您的审核意见已提交，请勿重复审核！',
				);
				$this->_get_type($array);
			}
			//执行审核
			$data = array(
				'content' => $this->input->post('content'),
				'status' => $this->input->post('status'),
				'time' => time(),
			);

			//判断连个专家意见是否一致 如果一致同意
			$other_suggest = $this->index_model->get_suggest_info(array('article_id' => $article_id, 'user_id !=' => $user_id, 'token' => $token));
			if (!empty($other_suggest[0]['status'])) {
				if ($data['status'] == $other_suggest[0]['status'] && $data['status'] == 1) {
					$check_status = array('check_status' => ($article[0]['check_status'] + 1));
				} elseif ($data['status'] != $other_suggest[0]['status']) {
					$check_status = array('check_status' => (-$article[0]['check_status'] - 10));
				} else {
					$check_status = array('check_status' => -1);
				}
				$check_status['check_token'] = ''; //两个专家完成审核后将审核票据清空
				$check_status['allot_status'] = 0; //将该稿件被指定状态设为0 即未指定审核
				$status = $this->db->update('article', $check_status, array('article_id' => $article_id));

				//判断稿件状态是否自动修改成功 否则是否审核失败不继续向下执行插入审核意见操作
				if (!$status) {
					$array = array(
						'code' => 400,
						'message' => '提交失败，请稍后重试！',
					);
				}
			}

			//执行提交意见操作
			if ($this->db->update('suggest', $data, array('sug_id' => $suggest[0]['sug_id']))) {
				$array = array(
					'code' => 200,
					'message' => '审核意见已经提交，感谢您参与审核！',
				);
			} else {
				$array = array(
					'code' => 400,
					'message' => '审核意见提交失败，请稍后重试！',
				);
			}
			$this->_get_type($array);
		} else {
			alert_msg('您访问的内容不存在！', 'close');
		}
	}

	/*
		下载稿件
	*/
	public function download($article_id, $action = '') {
		//article_id必须为数字，防止sql注入
		if (!is_numeric($article_id)) {
			alert_msg('无法进行下载！', 'close');
		}
		$where_arr = array('article_id' => $article_id);
		//判断是否从用户中心发来的下载请求
		$authority = true;
		if ($action == 'authority') {
			$where_arr['user_id'] = $this->session->userdata('user_id');
			$authority = false; //与check_status相或，取消判断稿件状态
		}

		//判断稿件是否存在
		$article = $this->db->select('title, check_status, attachment_url, check_token')->get_where('article', $where_arr)->result_array();
		if (empty($article)) {
			alert_msg('无法完成下载！', 'close');
		}

		$authority = $article[0]['check_token'] == $action && !empty($action) ? false : true;
		//如果用户不是专家或者编委，不能下载未经过编委会审核的稿件
		//0=未审核 1=初审完成 2=二审完成 3=编委会定稿完成 -1=拒稿
		$identity = $this->session->userdata('identity');

		if ($authority && $identity != 'specialist' && $identity != 'editorial' && $article[0]['check_status'] < 3) {
			//如果是未完成审核的稿件
			if ($article[0]['check_status'] < 3) {
				alert_msg('该稿件未完成审核，暂不能下载！');
			}
			alert_msg('无法完成下载！', 'close');
		}

		//执行下载
		$this->load->helper('download');
		$data = file_get_contents($this->config->item('MYPATH') . $article[0]['attachment_url']);

		//匹配文件后缀名，重命名下载
		preg_match('/.\w+$/', $article[0]['attachment_url'], $matches);
		force_download($article[0]['title'] . $matches[0], $data);
	}

	public function ceshi($action = 0) {
		// $status = 1;
		// $status && print_r('短路与，前面status为1，执行输出');
		// echo '<br>';
		// !$status && print_r('前面status为0,不执行输出');
		// echo '<br>';
		// $status || print_r('短路或，前面status为1，整个表达式值为1，后面不在执行');
		// echo '<br>';
		// !$status || print_r('前面status为0, 继续向后执行看后面是否成立，所以执行后面输出</br>');
		// $this->db->select('*')->get_where('user', array())->result_array();
		// echo $this->db->last_query();
		// if ($action == 'do') {
		// 	$config = array(
		// 		'upload_path' => './style/',
		// 		'allowed_types' => 'jpeg|jpg|png',

		// 	);
		// 	$this->load->library('upload', $config);
		// 	if ($this->upload->do_upload('myfile')) {
		// 		echo '文件上传成功';
		// 	} else {
		// 		$error = array('error' => $this->upload->display_errors());
		// 		print_r($error);
		// 	}
		// 	//print_r($_FILES);die;
		// 	// $myfile = array();
		// 	// for ($i = 0; $i < count($_FILES['myfile']['name']); $i++) {
		// 	// 	foreach ($_FILES['myfile'] as $key => $value) {
		// 	// 		$myfile[$i][$key] = $value[$i];
		// 	// 	}
		// 	// }
		// 	// $_FILES = array();
		// 	// //print_r($myfile);die;
		// 	// foreach ($myfile as $file) {
		// 	// 	$_FILES['file'] = $file;
		// 	// 	if ($this->upload->do_upload('file')) {
		// 	// 		echo '文件上传成功';
		// 	// 	} else {
		// 	// 		$error = array('error' => $this->upload->display_errors());
		// 	// 		print_r($error);
		// 	// 	}
		// 	// }

		// }
		$data['word'] = base_url('ueditor/y.docx');
		$this->load->view('ceshi.html', $data);
	}

	public function ceshi2($action = 'see') {
		if ($action == 'do') {
			$config = array(
				'upload_path' => './style/',
			);
			$this->load->library('upload', $config);
			print_r($_FILES);
		}
		$this->load->view('ceshi2.html');
	}
	/*
		验证码
	*/
	public function authcode() {
		if (!isset($_SESSION)) {
			session_start();
		}
		$img = imagecreatetruecolor(100, 40);
		$bgcolor = imagecolorallocate($img, rand(200, 255), rand(200, 255), rand(200, 255));
		imagefill($img, 0, 0, $bgcolor);
		$captch_code = "";
		$fontfile = $this->config->item('MYPATH') . 'Soopafresh.ttf';
		for ($i = 0; $i < 4; $i++) {
			$fontsize = 20;
			$fontcolor = imagecolorallocate($img, rand(0, 100), rand(0, 100), rand(0, 100));
			$date = "abcdefghjkmnpqrstuvwxyz23456789";
			$fontcontent = substr($date, rand(0, strlen($date)), 1);
			$captch_code .= $fontcontent;

			$x = ($i * 100 / 4) + rand(5, 10);
			$y = rand(25, 30);

			imagettftext($img, $fontsize, 0, $x, $y, $fontcolor, $fontfile, $fontcontent);
		}
		$this->session->set_userdata(array('user_authcode' => $captch_code));
		//点干扰
		for ($i = 0; $i < 200; $i++) {
			$pointcolor = imagecolorallocate($img, rand(50, 200), rand(50, 200), rand(50, 200));
			imagesetpixel($img, rand(1, 99), rand(1, 29), $pointcolor);

		}

		//线干扰
		for ($i = 0; $i < 3; $i++) {
			$linecolor = imagecolorallocate($img, rand(80, 220), rand(80, 220), rand(80, 220));
			imageline($img, rand(1, 99), rand(1, 29), rand(1, 99), rand(1, 29), $linecolor);
		}

		header('content-type:image/png');
		imagepng($img);
	}

	private function _get_type($data, $urldecode = 0, $type = 'json') {
		if ($type == 'array') {
			pring_r($data);
			exit;
		}
		if (!isset($data['code']) || !is_numeric($data['code'])) {
			return '';
		}
		if ($urldecode) {
			$json = urldecode(json_encode($data, JSON_UNESCAPED_UNICODE));
		} else {
			$json = json_encode($data, JSON_UNESCAPED_UNICODE);
		}
		echo $json;
		exit;
	}

	/*
		17级座次表
	 */
	public function mySite($action = 'see') {
		$data['site'] = json_decode(file_get_contents('site.json'));
		if ($action == 'set') {
			$row = $this->input->post('row');
			$col = $this->input->post('col');
			$name = $this->input->post('name');
			is_numeric($row) && is_numeric($col) && $row < 8 && $col < 4 ? '' : alert_msg('不要调皮！');
			$json_str = file_get_contents('site.json');
			$site_arr = json_decode($json_str);
			$site_arr[$row][$col] = $name;
			file_put_contents('site.json', json_encode($site_arr, JSON_UNESCAPED_UNICODE));
			$array = array(
				'code' => 200,
				'name' => $name,
				'row' => $row,
				'col' => $col,
			);
			$this->_get_type($array);
		} else {
			$this->load->view('my_site.html', $data);
		}
	}
	/************************ 前台业务逻辑 end ************************/
}
