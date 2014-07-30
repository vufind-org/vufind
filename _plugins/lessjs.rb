module Jekyll

  class LessCssFile < StaticFile
    def write(dest)
      # do nothing
    end
  end

# Expects a lessc: key in your _config.yml file with the path to a local less.js/bin/lessc
# Less.js will require node.js to be installed
  class LessJsGenerator < Generator
    safe true
    priority :low

    def generate(site)
      src_root = site.config['source']
      dest_root = site.config['destination']
      less_ext = /\.less$/i

      raise "Missing 'lessc' path in site configuration" if !site.config['lessc']

      # static_files have already been filtered against excludes, etc.
      site.static_files.each do |sf|
        next if not sf.path =~ less_ext

        less_path = sf.path
        css_path = less_path.gsub(less_ext, '.css').gsub(src_root, dest_root)
        css_dir = File.dirname(css_path)
        css_dir_relative = css_dir.gsub(dest_root, '')
        css_name = File.basename(css_path)

        FileUtils.mkdir_p(css_dir)

        begin
          command = [site.config['lessc'],
                     less_path,
                     css_path
                     ].join(' ')

          puts 'Compiling LESS: ' + command
          puts src_root

          `#{command}`

          # Build to root too
          command = [site.config['lessc'],
                     less_path,
                     less_path.gsub(less_ext, '.css').gsub(src_root, dest_root+'/..')
                     ].join(' ')
          puts 'Compiling LESS: ' + command

          `#{command}`

          raise "LESS compilation error" if $?.to_i != 0
        end

        # Add this output file so it won't be cleaned
        site.static_files << LessCssFile.new(site, site.source, css_dir_relative, css_name)
      end
    end
  end
end