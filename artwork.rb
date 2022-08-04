#!/usr/bin/env ruby
# frozen_string_literal: true

require 'json'
require 'net/http'
require 'open-uri'

items  = []
entity = ARGV[0]
search = ARGV[1]
country = ENV['country']

begin
  uri  = URI("http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsSearch?term=#{search}&country=#{country}&entity=#{entity}")
  http = Net::HTTP.new(uri.host, uri.port)
  req  = Net::HTTP::Get.new(uri)
  req.add_field 'Accept-Encoding', 'gzip, zlib, deflate, zstd, br'

  # Fetch Request
  res = http.request(req)

  raise 'Something went wrong with the request' unless res.code == '200'

  response = JSON.parse(res.body)
  search_results = response['results']

  search_results.each do |result|
    item          = {}
    item['uid']   = result['artworkUrl100']
    item['hires'] = result['artworkUrl100'].gsub('100x100', '1200x1200')
    item['title'] = case entity
                    when 'album'
                      "#{result['collectionName']} (by #{result['artistName']})"
                    when 'tvSeason'
                      result['collectionName']
                    when 'movie'
                      "#{result['trackName']}"
                    else
                      raise 'Unknown entity specified'
                    end

    # Cache 100px images for icons in results list
    Dir.mkdir(ENV['alfred_workflow_cache'], 0o755) unless File.directory?(ENV['alfred_workflow_cache'])

    icon = "#{ENV['alfred_workflow_cache']}/#{result['artistId']}-#{result['collectionId']}.jpg"
    URI.open(result['artworkUrl100']) do |image|
      File.open(icon, 'wb') do |file|
        file.write(image.read)
      end
    end

    item['icon']         = { 'path' => icon }
    item['quicklookurl'] = item['hires']
    item['arg']          = item['hires']
    item['text']         = { 'copy' => item['hires'] }
    items.append(item)
  end

  alfred_output = if items
                    { 'items' => items }.to_json
                  else
                    {
                      'items' => [
                        {
                          'title' => "No results found for #{search}."
                        }
                      ]
                    }.to_json
                  end

  puts alfred_output
rescue StandardError => e
  puts "HTTP Request failed (#{e.message})"
end
