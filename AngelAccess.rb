#!/usr/bin/env ruby -rubygems

require 'mechanize'
require 'net/https'
require 'highline/import'
require 'uri'
require 'fileutils'

class AngelAccess
  class InvalidLogin < StandardError; end

  def initialize
    @agent = Mechanize.new
  end

  def angel_get(path)
    @agent.get "https://uwangel.uwaterloo.ca/uwangel/#{path}"
  end

  def login
    @username ||= ask("Username: ")
    @password ||= ask("Password: ") {|q| q.echo = '*'}

    say "Logging in ..."

    login_page = angel_get 'home.asp'
    form = login_page.form_with(:name => "frmLogon")
    form.username = @username
    form.password = @password

    login_submit_page = @agent.submit form

    if login_submit_page.uri.to_s.include? 'authenticate.asp'
      raise InvalidLogin
    end
  rescue InvalidLogin
    say 'Invalid Username/Password'
    exit
  end

  def get_course_links
    class_list_page = angel_get 'default.asp'
    class_list_page.links_with(:href => /section\/default/)
  end

  def get_node_type(li_node)
    li_node['class'].split(' ').find{|c| c =~ /^cmType/}.gsub(/^cmType/,'').downcase
  end

  def get_node_name(li_node)
    li_node.css('div.title a').children.find{|c| c.class == Nokogiri::XML::Text}.content
  end

  def get_download_path(page_url)
    dl_page = @agent.get((@agent.get page_url).iframes.first.src)
    dl_page.link_with(:href => /AngelUploads/).href
  rescue NoMethodError
    nil
  end

  def dir_sanitize(dirname)
    dirname.gsub(/[^a-zA-Z0-9 _]/,'').strip
  end

  def traverse(dir_listing_page)
    tree = []  

    dir_listing_page.parser.css('ul.directory li').collect do |li|
      node_type = get_node_type(li)
      node_name = get_node_name(li)

      targ_href = li.css('div.title a').first['href']

      {
        :type => node_type,
        :name => node_name,
        :url  => targ_href,
        :children => (node_type == 'folder')? traverse(@agent.get targ_href) : []
      }
    end
  end

  def get_directory_tree(course_link)
    course_page = @agent.click course_link  
    content_page = @agent.click course_page.link_with(:href => /content/)
    traverse(content_page)
  end

  def print_directory_tree(dir_tree,depth = 0)
    dir_tree.each do |node|
      print "%s%s %s\n" % [
        "\t" * depth,
        (node[:children].size > 0)?'+':'-',
        node[:name],
      ]
      print_directory_tree(node[:children],depth+1)
    end
  end

  def download_tree(dir_tree,basedir,depth=0)
    FileUtils.mkdir_p(basedir)
    dir_tree.each do |node|
      if node[:type] == 'file'

        download_path = get_download_path node[:url]
        basename = File.basename(download_path)
        save_path = File.join(basedir, basename)

        print "%s- %s - " % ["\t" * depth, basename]

        if download_path.nil?
          puts "Failed (Skipping...)"
        elsif File.exists? save_path
          puts "Skipping..."
          next
        else
          print "Saving... "
        end

        uri = URI.parse("https://uwangel.uwaterloo.ca")
        https = Net::HTTP.new(uri.host, uri.port)
        https.use_ssl = true
        https.verify_mode = OpenSSL::SSL::VERIFY_NONE

        request = Net::HTTP::Get.new(uri.request_uri)

        response = https.request(request)

        open(save_path,"wb") do |file|
          file.write(response.body)
        end

        puts "Done."
      elsif node[:type] == 'folder'
        puts "%s+ %s" % ["\t" * depth, node[:name]]
        download_tree(node[:children],File.join(basedir, dir_sanitize(node[:name])),depth+1)
      end
    end
  end

  def download_all
    login

    get_course_links.each do |course_link|
      puts course_link.text
      puts '='*course_link.text.length
      if (course_link.text.downcase =~ /pdeng/)
        puts "Skipping PDEng Course..."
        next
      end

      if (course_link.text.downcase =~ /co-op/)
        puts "Skipping Co-Op Course..."
        next
      end
      dir_tree = get_directory_tree(course_link)
      #print_directory_tree(dir_tree)

      download_tree(dir_tree,File.join("uwace_#{@username}",dir_sanitize(course_link.text)))
    end
  end
end

AngelAccess.new.download_all
