// pages/lottery/records/records.js
const app = getApp()
const api = require('../../utils/api.js')

Page({
  data: {
    currentTab: 0,
    page: 1,
    limit: 10,
    records: [],
    loading: false,
    hasMore: true
  },

  onLoad: function(options) {
    this.loadRecords()
  },

  // 切换标签
  switchTab: function(e) {
    const idx = e.currentTarget.dataset.idx
    this.setData({
      currentTab: idx,
      page: 1,
      records: [],
      hasMore: true
    })
    this.loadRecords()
  },

  // 加载记录
  loadRecords: function() {
    if (this.data.loading || !this.data.hasMore) return
    
    this.setData({ loading: true })
    
    api.request({
      url: '/api/lottery/records',
      data: {
        page: this.data.page,
        limit: this.data.limit,
        status: this.data.currentTab == 0 ? '' : this.data.currentTab
      }
    }).then(res => {
      if (res.code === 1) {
        const list = (res.data || []).map(item => {
          item.create_time_text = this.formatTime(item.create_time)
          item.status_text = this.getStatusText(item.status)
          item.prize_image = item.prize_image || ''
          return item
        })
        
        this.setData({
          records: this.data.page === 1 ? list : this.data.records.concat(list),
          page: this.data.page + 1,
          hasMore: list.length >= this.data.limit,
          loading: false
        })
      } else {
        this.setData({ loading: false })
        wx.showToast({
          title: res.msg || '加载失败',
          icon: 'none'
        })
      }
    }).catch(() => {
      this.setData({ loading: false })
    })
  },

  // 格式化时间
  formatTime: function(timestamp) {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getFullYear()}-${this.pad(date.getMonth()+1)}-${this.pad(date.getDate())} ${this.pad(date.getHours())}:${this.pad(date.getMinutes())}`
  },

  pad: function(n) {
    return n < 10 ? '0' + n : n
  },

  // 获取状态文本
  getStatusText: function(status) {
    const texts = {
      1: '待领取',
      2: '已领取',
      3: '已过期'
    }
    return texts[status] || '未知'
  },

  // 上拉加载更多
  onReachBottom: function() {
    this.loadRecords()
  },

  // 下拉刷新
  onPullDownRefresh: function() {
    this.setData({
      page: 1,
      records: [],
      hasMore: true
    })
    this.loadRecords().then(() => {
      wx.stopPullDownRefresh()
    })
  }
})