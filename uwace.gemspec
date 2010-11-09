Gem::Specification.new do |s|
  s.name        = 'uwace'
  s.version     = '1.0.1'
  s.platform    = Gem::Platform::RUBY
  s.authors     = ['Jamie Wong']
  s.summary     = 'Download UWAce'
  s.description = 'Download all files from UWAce, skipping ones already saved'
  s.email       = ['jamie.lf.wong@gmail.com']
  s.homepage    = 'https://github.com/phleet/UWAngel-CLI'
  s.add_dependency 'highline',  '>= 1.6.1'
  s.add_dependency 'mechanize', '>= 1.0.0'
  s.add_dependency 'rainbow', '>= 1.1'

  s.files               =  ['bin/uwace']
  s.executables         =  ['uwace'] 
end
